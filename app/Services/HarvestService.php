<?php

namespace App\Services;

use App\Enums\Commodity;
use App\Enums\PayrollStatus;
use App\Enums\ProductionDocumentStatus;
use App\Models\Harvest;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Models\WorkerPayroll;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HarvestService
{
    public function __construct(private readonly HarvestAvailabilityService $availability) {}

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id?: int|null,
     *     work_activity_id?: int|null,
     *     harvest_date: string,
     *     commodity: string,
     *     quantity: float|int|string,
     *     unit: string,
     *     bunch_count?: int|null,
     *     quality_grade?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function create(PlantationEntity $entity, array $data): Harvest
    {
        return DB::transaction(function () use ($entity, $data) {
            $this->assertLocationAndActivity($entity, $data);

            return $entity->harvests()->create([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'] ?? null,
                'work_activity_id' => $data['work_activity_id'] ?? null,
                'harvest_date' => $data['harvest_date'],
                'commodity' => Commodity::from($data['commodity']),
                'quantity' => $this->positiveQuantity($data['quantity']),
                'unit' => $data['unit'],
                'bunch_count' => $data['bunch_count'] ?? null,
                'quality_grade' => $data['quality_grade'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => ProductionDocumentStatus::DRAFT,
            ]);
        });
    }

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id?: int|null,
     *     work_activity_id?: int|null,
     *     harvest_date: string,
     *     commodity: string,
     *     quantity: float|int|string,
     *     unit: string,
     *     bunch_count?: int|null,
     *     quality_grade?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function update(Harvest $harvest, array $data): Harvest
    {
        return DB::transaction(function () use ($harvest, $data) {
            $locked = Harvest::query()->whereKey($harvest->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);
            $this->assertLocationAndActivity($locked->plantationEntity, $data);

            $locked->update([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'] ?? null,
                'work_activity_id' => $data['work_activity_id'] ?? null,
                'harvest_date' => $data['harvest_date'],
                'commodity' => Commodity::from($data['commodity']),
                'quantity' => $this->positiveQuantity($data['quantity']),
                'unit' => $data['unit'],
                'bunch_count' => $data['bunch_count'] ?? null,
                'quality_grade' => $data['quality_grade'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function post(Harvest $harvest): Harvest
    {
        return DB::transaction(function () use ($harvest) {
            $locked = Harvest::query()->whereKey($harvest->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Panen yang dibatalkan tidak dapat diposting ulang.');
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            $locked->update(['status' => ProductionDocumentStatus::POSTED]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function cancel(Harvest $harvest, string $reason = 'Panen dibatalkan'): Harvest
    {
        return DB::transaction(function () use ($harvest, $reason) {
            $locked = Harvest::query()->whereKey($harvest->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }

            if ($locked->isPosted() && $this->availability->hasPostedSales($locked)) {
                throw new InvalidArgumentException('Panen yang sudah terjual tidak dapat dibatalkan. Batalkan penjualan terkait terlebih dahulu.');
            }

            $locked->update([
                'status' => ProductionDocumentStatus::CANCELLED,
                'cancelled_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function deleteDraft(Harvest $harvest): void
    {
        if (! $harvest->isDraft()) {
            throw new InvalidArgumentException('Hanya panen draft yang dapat dihapus.');
        }

        if ($harvest->saleItems()->exists()) {
            throw new InvalidArgumentException('Panen yang sudah terkait penjualan tidak dapat dihapus.');
        }

        $harvest->delete();
    }

    public function activityLaborCost(WorkActivity $activity): string
    {
        $sum = WorkerPayroll::query()
            ->where('work_activity_id', $activity->id)
            ->where('payroll_status', PayrollStatus::POSTED)
            ->sum('final_amount');

        return Money::normalize($sum ?: '0');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLocationAndActivity(PlantationEntity $entity, array $data): void
    {
        $plantation = Plantation::query()->forEntity($entity)->whereKey($data['plantation_id'])->first();
        if ($plantation === null) {
            throw new InvalidArgumentException('Kebun tidak milik unit ini.');
        }

        $block = null;
        if (! empty($data['plantation_block_id'])) {
            $block = PlantationBlock::query()->whereKey($data['plantation_block_id'])->first();
            if ($block === null || (int) $block->plantation_id !== (int) $plantation->id) {
                throw new InvalidArgumentException('Blok harus milik kebun yang dipilih.');
            }
        }

        if (empty($data['work_activity_id'])) {
            return;
        }

        $activity = WorkActivity::query()->forEntity($entity)->whereKey($data['work_activity_id'])->first();
        if ($activity === null) {
            throw new InvalidArgumentException('Aktivitas kerja tidak milik unit ini.');
        }

        if ((int) $activity->plantation_id !== (int) $plantation->id) {
            throw new InvalidArgumentException('Aktivitas kerja harus pada kebun yang sama.');
        }

        if ($activity->plantation_block_id && $block && (int) $activity->plantation_block_id !== (int) $block->id) {
            throw new InvalidArgumentException('Blok panen harus sesuai dengan blok aktivitas kerja.');
        }
    }

    private function assertDraft(Harvest $harvest): void
    {
        if (! $harvest->isDraft()) {
            throw new InvalidArgumentException('Panen yang sudah diposting tidak dapat diubah.');
        }
    }

    private function positiveQuantity(mixed $quantity): string
    {
        $qty = Quantity::normalize($quantity);
        if (! Quantity::isPositive($qty)) {
            throw new InvalidArgumentException('Kuantitas panen harus lebih dari 0.');
        }

        return $qty;
    }
}
