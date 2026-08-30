<?php

namespace App\Services;

use App\Enums\ProductionDocumentStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Buyer;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\PlantationEntity;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HarvestSaleService
{
    public function __construct(
        private readonly HarvestAvailabilityService $availability,
        private readonly IntegrationOutboxService $outbox,
    ) {}

    /**
     * @param  array{
     *     buyer_id: int,
     *     sale_date: string,
     *     invoice_number?: string|null,
     *     description?: string|null,
     *     adjustment_amount?: float|int|string,
     *     items: list<array{harvest_id: int, quantity: float|int|string, unit_price: float|int|string}>
     * }  $data
     */
    public function create(PlantationEntity $entity, array $data): HarvestSale
    {
        return DB::transaction(function () use ($entity, $data) {
            $this->assertBuyer($entity, (int) $data['buyer_id'], requireActive: true);

            $sale = $entity->harvestSales()->create([
                'buyer_id' => $data['buyer_id'],
                'sale_date' => $data['sale_date'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'] ?? null,
                'adjustment_amount' => Money::normalize($data['adjustment_amount'] ?? '0'),
                'subtotal' => Money::normalize('0'),
                'total_amount' => Money::normalize('0'),
                'status' => ProductionDocumentStatus::DRAFT,
                'payment_status' => SalePaymentStatus::UNPAID,
            ]);

            $this->replaceItems($sale, $entity, $data['items']);
            $this->recalculate($sale);

            return $sale->fresh(['items']) ?? $sale;
        });
    }

    /**
     * @param  array{
     *     buyer_id: int,
     *     sale_date: string,
     *     invoice_number?: string|null,
     *     description?: string|null,
     *     adjustment_amount?: float|int|string,
     *     items: list<array{harvest_id: int, quantity: float|int|string, unit_price: float|int|string}>
     * }  $data
     */
    public function update(HarvestSale $sale, array $data): HarvestSale
    {
        return DB::transaction(function () use ($sale, $data) {
            $locked = HarvestSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);

            $entity = $locked->plantationEntity;
            $this->assertBuyer($entity, (int) $data['buyer_id'], requireActive: true);

            $locked->update([
                'buyer_id' => $data['buyer_id'],
                'sale_date' => $data['sale_date'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'] ?? null,
                'adjustment_amount' => Money::normalize($data['adjustment_amount'] ?? '0'),
            ]);

            $this->replaceItems($locked, $entity, $data['items']);
            $this->recalculate($locked);

            return $locked->fresh(['items']) ?? $locked;
        });
    }

    public function post(HarvestSale $sale): HarvestSale
    {
        return DB::transaction(function () use ($sale) {
            $locked = HarvestSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Penjualan yang dibatalkan tidak dapat diposting ulang.');
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            $locked->load('items');
            $harvestIds = $locked->items->pluck('harvest_id')->unique()->sort()->values();

            $harvests = Harvest::query()
                ->whereIn('id', $harvestIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $requestedByHarvest = [];
            foreach ($locked->items as $line) {
                $harvest = $harvests->get($line->harvest_id);
                if ($harvest === null || (int) $harvest->plantation_entity_id !== (int) $locked->plantation_entity_id) {
                    throw new InvalidArgumentException('Hasil panen tidak milik unit ini.');
                }

                $requestedByHarvest[$harvest->id] = Quantity::add(
                    $requestedByHarvest[$harvest->id] ?? '0',
                    $line->quantity
                );
            }

            foreach ($requestedByHarvest as $harvestId => $qty) {
                $this->availability->assertCanSell($harvests->get($harvestId), $qty);
            }

            $this->recalculate($locked);

            $locked->update([
                'status' => ProductionDocumentStatus::POSTED,
                'payment_status' => SalePaymentStatus::UNPAID,
            ]);

            $posted = $locked->fresh(['items.harvest', 'buyer', 'plantationEntity']) ?? $locked;
            $this->outbox->recordSalePosted($posted);

            return $posted;
        });
    }

    public function cancel(HarvestSale $sale, string $reason = 'Penjualan dibatalkan'): HarvestSale
    {
        return DB::transaction(function () use ($sale, $reason) {
            $locked = HarvestSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }

            $wasPosted = $locked->isPosted();

            if ($wasPosted) {
                $paid = app(HarvestSalePaymentService::class)->paidAmount($locked);
                if (Money::cmp($paid, '0') === 1) {
                    throw new InvalidArgumentException('Penjualan yang sudah memiliki pembayaran tidak dapat dibatalkan. Batalkan pembayaran terlebih dahulu.');
                }
            }

            $locked->update([
                'status' => ProductionDocumentStatus::CANCELLED,
                'cancelled_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $cancelled = $locked->fresh(['buyer', 'plantationEntity']) ?? $locked;
            if ($wasPosted) {
                $this->outbox->recordSaleCancelled($cancelled);
            }

            return $cancelled;
        });
    }

    public function deleteDraft(HarvestSale $sale): void
    {
        if (! $sale->isDraft()) {
            throw new InvalidArgumentException('Hanya penjualan draft yang dapat dihapus.');
        }

        if ($sale->payments()->exists()) {
            throw new InvalidArgumentException('Penjualan yang memiliki histori pembayaran tidak dapat dihapus.');
        }

        $sale->items()->delete();
        $sale->delete();
    }

    /**
     * @param  list<array{harvest_id: int, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    private function replaceItems(HarvestSale $sale, PlantationEntity $entity, array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Penjualan harus memiliki minimal satu hasil panen.');
        }

        $sale->items()->delete();

        foreach ($items as $line) {
            $harvest = Harvest::query()->forEntity($entity)->whereKey($line['harvest_id'])->first();
            if ($harvest === null) {
                throw new InvalidArgumentException('Hasil panen tidak milik unit ini.');
            }

            if (! $harvest->isPosted()) {
                throw new InvalidArgumentException('Hanya panen yang sudah diposting yang dapat dijual.');
            }

            $quantity = Quantity::normalize($line['quantity']);
            if (! Quantity::isPositive($quantity)) {
                throw new InvalidArgumentException('Kuantitas penjualan harus lebih dari 0.');
            }

            $unitPrice = Money::normalize($line['unit_price']);
            if (Money::cmp($unitPrice, '0') === -1) {
                throw new InvalidArgumentException('Harga satuan tidak boleh negatif.');
            }

            $sale->items()->create([
                'harvest_id' => $harvest->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => Money::lineTotal($quantity, $unitPrice),
            ]);
        }
    }

    private function recalculate(HarvestSale $sale): void
    {
        $sale->load('items');
        $subtotal = Money::normalize('0');
        foreach ($sale->items as $line) {
            $lineTotal = Money::lineTotal($line->quantity, $line->unit_price);
            if ($line->line_total !== $lineTotal) {
                $line->update(['line_total' => $lineTotal]);
            }
            $subtotal = Money::add($subtotal, $lineTotal);
        }

        $total = Money::add($subtotal, $sale->adjustment_amount);
        if (Money::cmp($total, '0') === -1) {
            throw new InvalidArgumentException('Total penjualan tidak boleh negatif.');
        }

        $sale->update([
            'subtotal' => $subtotal,
            'total_amount' => $total,
        ]);
        $sale->subtotal = $subtotal;
        $sale->total_amount = $total;
    }

    private function assertDraft(HarvestSale $sale): void
    {
        if (! $sale->isDraft()) {
            throw new InvalidArgumentException('Penjualan yang sudah diposting tidak dapat diubah.');
        }
    }

    private function assertBuyer(PlantationEntity $entity, int $buyerId, bool $requireActive): void
    {
        $buyer = Buyer::query()->forEntity($entity)->whereKey($buyerId)->first();
        if ($buyer === null) {
            throw new InvalidArgumentException('Pembeli tidak milik unit ini.');
        }

        if ($requireActive && ! $buyer->is_active) {
            throw new InvalidArgumentException('Pembeli tidak aktif tidak dapat dipakai untuk penjualan baru.');
        }
    }
}
