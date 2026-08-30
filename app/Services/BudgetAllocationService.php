<?php

namespace App\Services;

use App\Enums\RealizationSourceType;
use App\Enums\RealizationStatus;
use App\Models\BudgetAllocationItem;
use App\Models\BudgetRealization;
use App\Models\FinanceBudgetAllocation;
use App\Models\PlantationEntity;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BudgetAllocationService
{
    /**
     * @param  array{budget_public_id: string, finance_entity_public_id: string, name: string, period_start: string, period_end: string, allocated_amount: float|int|string}  $payload
     */
    public function upsertFromFinance(array $payload): FinanceBudgetAllocation
    {
        $entity = PlantationEntity::query()
            ->where('finance_entity_public_id', $payload['finance_entity_public_id'])
            ->first();

        if (! $entity instanceof PlantationEntity) {
            throw new InvalidArgumentException('Unit kebun tidak ditemukan untuk Finance Entity tersebut.');
        }

        $budgetPublicId = $payload['budget_public_id'];
        $amount = number_format((float) $payload['allocated_amount'], 2, '.', '');

        return DB::transaction(function () use ($entity, $payload, $budgetPublicId, $amount) {
            $existing = FinanceBudgetAllocation::query()
                ->where('finance_budget_public_id', $budgetPublicId)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof FinanceBudgetAllocation
                && (int) $existing->plantation_entity_id !== (int) $entity->id) {
                throw new InvalidArgumentException('Anggaran ini sudah dialokasikan ke unit kebun lain.');
            }

            if ($existing instanceof FinanceBudgetAllocation) {
                $itemsSum = $existing->itemsAllocatedTotal();

                if (bccomp($itemsSum, $amount, 2) === 1) {
                    throw new InvalidArgumentException(
                        'Jumlah alokasi tidak boleh lebih kecil dari total pembagian yang sudah ada di kebun.'
                    );
                }
            }

            return FinanceBudgetAllocation::query()->updateOrCreate(
                ['finance_budget_public_id' => $budgetPublicId],
                [
                    'plantation_entity_id' => $entity->id,
                    'name' => $payload['name'],
                    'period_start' => $payload['period_start'],
                    'period_end' => $payload['period_end'],
                    'allocated_amount' => $amount,
                    'status' => 'ACTIVE',
                    'synced_at' => now(),
                ],
            );
        });
    }

    /**
     * @param  array{category: string, name: string, allocated_amount: float|int|string}  $data
     */
    public function addItem(FinanceBudgetAllocation $allocation, array $data): BudgetAllocationItem
    {
        return DB::transaction(function () use ($allocation, $data) {
            $locked = FinanceBudgetAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            $amount = number_format((float) $data['allocated_amount'], 2, '.', '');
            $this->assertItemsFit($locked, $amount);

            return $locked->items()->create([
                'category' => $data['category'],
                'name' => $data['name'],
                'allocated_amount' => $amount,
            ]);
        });
    }

    /**
     * @param  array{category: string, name: string, allocated_amount: float|int|string}  $data
     */
    public function updateItem(BudgetAllocationItem $item, array $data): BudgetAllocationItem
    {
        return DB::transaction(function () use ($item, $data) {
            $lockedItem = BudgetAllocationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $allocation = FinanceBudgetAllocation::query()
                ->whereKey($lockedItem->finance_budget_allocation_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = number_format((float) $data['allocated_amount'], 2, '.', '');
            $realized = $lockedItem->realizedTotal();

            if (bccomp($realized, $amount, 2) === 1) {
                throw new InvalidArgumentException('Jumlah item tidak boleh lebih kecil dari realisasi yang sudah tercatat.');
            }

            $delta = bcsub($amount, (string) $lockedItem->allocated_amount, 2);
            $this->assertItemsFit($allocation, $delta);

            $lockedItem->update([
                'category' => $data['category'],
                'name' => $data['name'],
                'allocated_amount' => $amount,
            ]);

            return $lockedItem->fresh() ?? $lockedItem;
        });
    }

    public function deleteItem(BudgetAllocationItem $item): void
    {
        if ($item->realizations()->exists()) {
            throw new InvalidArgumentException('Item yang sudah memiliki realisasi tidak dapat dihapus.');
        }

        $item->delete();
    }

    /**
     * @param  array{amount: float|int|string, realization_date: string, description?: string|null}  $data
     */
    public function addRealization(BudgetAllocationItem $item, array $data): BudgetRealization
    {
        return DB::transaction(function () use ($item, $data) {
            $locked = BudgetAllocationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $amount = Money::normalize($data['amount']);
            $nextTotal = Money::add($locked->realizedTotal(), $amount);

            if (bccomp($nextTotal, (string) $locked->allocated_amount, 2) === 1) {
                throw new InvalidArgumentException(
                    'Realisasi tidak boleh melebihi alokasi item. Over-budget memerlukan persetujuan tersendiri.'
                );
            }

            return $locked->realizations()->create([
                'source_type' => RealizationSourceType::MANUAL,
                'source_public_id' => (string) Str::ulid(),
                'amount' => $amount,
                'realization_date' => $data['realization_date'],
                'description' => $data['description'] ?? null,
                'status' => RealizationStatus::ACTIVE,
            ]);
        });
    }

    /**
     * Idempotent by (source_type, source_public_id) while ACTIVE.
     *
     * @param  array{source_type: RealizationSourceType|string, source_public_id: string, amount: float|int|string, realization_date: string, description?: string|null}  $data
     */
    public function recordSourceRealization(BudgetAllocationItem $item, array $data): BudgetRealization
    {
        $sourceType = $data['source_type'] instanceof RealizationSourceType
            ? $data['source_type']
            : RealizationSourceType::from((string) $data['source_type']);

        return DB::transaction(function () use ($item, $data, $sourceType) {
            $locked = BudgetAllocationItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $existing = BudgetRealization::query()
                ->where('source_type', $sourceType->value)
                ->where('source_public_id', $data['source_public_id'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof BudgetRealization && $existing->isActive()) {
                return $existing;
            }

            if ($existing instanceof BudgetRealization) {
                throw new InvalidArgumentException('Realisasi untuk sumber ini sudah dibatalkan dan tidak diaktifkan ulang.');
            }

            $amount = Money::normalize($data['amount']);
            $nextTotal = Money::add($locked->realizedTotal(), $amount);

            if (Money::cmp($nextTotal, $locked->allocated_amount) === 1) {
                throw new InvalidArgumentException(
                    'Realisasi tidak boleh melebihi alokasi item. Over-budget memerlukan persetujuan tersendiri.'
                );
            }

            return $locked->realizations()->create([
                'source_type' => $sourceType,
                'source_public_id' => $data['source_public_id'],
                'amount' => $amount,
                'realization_date' => $data['realization_date'],
                'description' => $data['description'] ?? null,
                'status' => RealizationStatus::ACTIVE,
            ]);
        });
    }

    public function reverseRealization(BudgetRealization $realization, string $reason): BudgetRealization
    {
        return DB::transaction(function () use ($realization, $reason) {
            $locked = BudgetRealization::query()->whereKey($realization->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RealizationStatus::REVERSED) {
                return $locked;
            }

            $locked->update([
                'status' => RealizationStatus::REVERSED,
                'reversed_at' => now(),
                'reversed_reason' => $reason,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function deleteRealization(BudgetRealization $realization): void
    {
        $this->reverseRealization($realization, 'Dibatalkan dari UI anggaran');
    }

    private function assertItemsFit(FinanceBudgetAllocation $allocation, string $additionalAmount): void
    {
        $next = bcadd($allocation->itemsAllocatedTotal(), $additionalAmount, 2);

        if (bccomp($next, (string) $allocation->allocated_amount, 2) === 1) {
            throw new InvalidArgumentException('Total pembagian tidak boleh melebihi alokasi dari Finance.');
        }
    }
}
