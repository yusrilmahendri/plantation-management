<?php

namespace App\Services;

use App\Enums\Commodity;
use App\Enums\PaymentRecordStatus;
use App\Enums\ProductionDocumentStatus;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\HarvestSaleItem;
use App\Models\HarvestSalePayment;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;

class ProductionReportService
{
    /**
     * @param  array{period_start?: string|null, period_end?: string|null, plantation_id?: int|null, plantation_block_id?: int|null, commodity?: string|null}  $filters
     * @return array{
     *     groups: list<array{commodity: string, commodity_label: string, unit: string, production: string, harvest_count: int, sold: string, remaining: string}>,
     *     sales_amount: string,
     *     received: string,
     *     outstanding: string,
     *     harvest_count: int
     * }
     */
    public function summary(PlantationEntity $entity, array $filters = []): array
    {
        $harvests = $this->postedHarvests($entity, $filters);
        $groups = [];

        foreach ($harvests as $harvest) {
            $key = $harvest->commodity->value.'|'.$harvest->unit;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'commodity' => $harvest->commodity->value,
                    'commodity_label' => $harvest->commodity->label(),
                    'unit' => $harvest->unit,
                    'production' => Quantity::normalize('0'),
                    'harvest_count' => 0,
                    'sold' => Quantity::normalize('0'),
                    'remaining' => Quantity::normalize('0'),
                ];
            }

            $sold = app(HarvestAvailabilityService::class)->soldQuantity($harvest);
            $groups[$key]['production'] = Quantity::add($groups[$key]['production'], $harvest->quantity);
            $groups[$key]['sold'] = Quantity::add($groups[$key]['sold'], $sold);
            $groups[$key]['remaining'] = Quantity::add($groups[$key]['remaining'], Quantity::sub($harvest->quantity, $sold));
            $groups[$key]['harvest_count']++;
        }

        $sales = $this->postedSales($entity, $filters);
        $salesAmount = Money::normalize('0');
        $received = Money::normalize('0');
        foreach ($sales as $sale) {
            $salesAmount = Money::add($salesAmount, $sale->total_amount);
            $received = Money::add($received, $sale->paidAmount());
        }

        return [
            'groups' => array_values($groups),
            'sales_amount' => $salesAmount,
            'received' => $received,
            'outstanding' => Money::cmp(Money::sub($salesAmount, $received), '0') === -1
                ? Money::normalize('0')
                : Money::sub($salesAmount, $received),
            'harvest_count' => $harvests->count(),
        ];
    }

    /**
     * @return array{month: string, year: string, total: string, yield_per_hectare: string|null, last_harvest: Harvest|null, sold: string}
     */
    public function blockMetrics(PlantationBlock $block): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $yearEnd = now()->endOfYear()->toDateString();

        $all = Harvest::query()
            ->where('plantation_block_id', $block->id)
            ->where('status', ProductionDocumentStatus::POSTED)
            ->get();

        $byCommodity = $all->groupBy(fn (Harvest $harvest) => $harvest->commodity->value.'|'.$harvest->unit);

        $groups = [];
        foreach ($byCommodity as $key => $rows) {
            [$commodity, $unit] = explode('|', $key, 2);
            $month = $rows->filter(fn (Harvest $harvest) => $harvest->harvest_date->toDateString() >= $monthStart
                && $harvest->harvest_date->toDateString() <= $monthEnd);
            $year = $rows->filter(fn (Harvest $harvest) => $harvest->harvest_date->toDateString() >= $yearStart
                && $harvest->harvest_date->toDateString() <= $yearEnd);

            $total = $rows->reduce(fn (string $carry, Harvest $harvest) => Quantity::add($carry, $harvest->quantity), '0.000');
            $sold = $rows->reduce(
                fn (string $carry, Harvest $harvest) => Quantity::add($carry, app(HarvestAvailabilityService::class)->soldQuantity($harvest)),
                '0.000'
            );

            $yield = null;
            if ($block->area !== null && Quantity::cmp($block->area, '0') === 1) {
                $yield = bcdiv($total, Quantity::normalize($block->area), 3);
            }

            $groups[] = [
                'commodity' => $commodity,
                'commodity_label' => Commodity::from($commodity)->label(),
                'unit' => $unit,
                'month' => $month->reduce(fn (string $carry, Harvest $harvest) => Quantity::add($carry, $harvest->quantity), '0.000'),
                'year' => $year->reduce(fn (string $carry, Harvest $harvest) => Quantity::add($carry, $harvest->quantity), '0.000'),
                'total' => $total,
                'sold' => $sold,
                'yield_per_hectare' => $yield,
            ];
        }

        return [
            'groups' => $groups,
            'last_harvest' => $all->sortByDesc('harvest_date')->first(),
        ];
    }

    /**
     * @return Collection<int, Harvest>
     */
    private function postedHarvests(PlantationEntity $entity, array $filters): Collection
    {
        return Harvest::query()
            ->forEntity($entity)
            ->where('status', ProductionDocumentStatus::POSTED)
            ->when($filters['period_start'] ?? null, fn ($query, $start) => $query->whereDate('harvest_date', '>=', $start))
            ->when($filters['period_end'] ?? null, fn ($query, $end) => $query->whereDate('harvest_date', '<=', $end))
            ->when($filters['plantation_id'] ?? null, fn ($query, $id) => $query->where('plantation_id', $id))
            ->when($filters['plantation_block_id'] ?? null, fn ($query, $id) => $query->where('plantation_block_id', $id))
            ->when($filters['commodity'] ?? null, fn ($query, $commodity) => $query->where('commodity', $commodity))
            ->get();
    }

    /**
     * @return Collection<int, HarvestSale>
     */
    private function postedSales(PlantationEntity $entity, array $filters): Collection
    {
        $harvestIds = $this->postedHarvests($entity, $filters)->pluck('id');

        if (($filters['plantation_id'] ?? null) || ($filters['plantation_block_id'] ?? null) || ($filters['commodity'] ?? null)) {
            $saleIds = HarvestSaleItem::query()->whereIn('harvest_id', $harvestIds)->pluck('harvest_sale_id');

            return HarvestSale::query()
                ->forEntity($entity)
                ->where('status', ProductionDocumentStatus::POSTED)
                ->whereIn('id', $saleIds)
                ->when($filters['period_start'] ?? null, fn ($query, $start) => $query->whereDate('sale_date', '>=', $start))
                ->when($filters['period_end'] ?? null, fn ($query, $end) => $query->whereDate('sale_date', '<=', $end))
                ->get();
        }

        return HarvestSale::query()
            ->forEntity($entity)
            ->where('status', ProductionDocumentStatus::POSTED)
            ->when($filters['period_start'] ?? null, fn ($query, $start) => $query->whereDate('sale_date', '>=', $start))
            ->when($filters['period_end'] ?? null, fn ($query, $end) => $query->whereDate('sale_date', '<=', $end))
            ->get();
    }

    /**
     * @return list<array{label: string, quantity: string}>
     */
    public function unsoldGroups(PlantationEntity $entity): array
    {
        $harvests = Harvest::query()->forEntity($entity)->where('status', ProductionDocumentStatus::POSTED)->get();
        $groups = [];
        foreach ($harvests as $harvest) {
            $remaining = app(HarvestAvailabilityService::class)->availableQuantity($harvest);
            if (Quantity::cmp($remaining, '0') !== 1) {
                continue;
            }
            $key = $harvest->commodity->label().' · '.$harvest->unit;
            $groups[$key] = Quantity::add($groups[$key] ?? '0', $remaining);
        }

        return collect($groups)->map(fn ($qty, $label) => ['label' => $label, 'quantity' => $qty])->values()->all();
    }

    public function receivedThisMonth(PlantationEntity $entity): string
    {
        $sum = HarvestSalePayment::query()
            ->where('status', PaymentRecordStatus::ACTIVE)
            ->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->whereHas('sale', fn ($query) => $query->forEntity($entity)->where('status', ProductionDocumentStatus::POSTED))
            ->sum('amount');

        return Money::normalize($sum ?: '0');
    }
}
