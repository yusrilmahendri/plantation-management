<?php

namespace App\Services;

use App\Enums\InventoryCategory;
use App\Enums\InventoryDocumentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\FertilizerApplication;
use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Models\WorkActivity;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FertilizerApplicationService
{
    public function __construct(private readonly InventoryStockService $stock) {}

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id: int,
     *     application_date: string,
     *     work_activity_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, dosage_per_plant?: float|int|string|null, plant_count?: int|null, notes?: string|null}>
     * }  $data
     */
    public function create(PlantationEntity $entity, array $data): FertilizerApplication
    {
        return DB::transaction(function () use ($entity, $data) {
            $this->assertLocation($entity, $data['plantation_id'], $data['plantation_block_id']);
            $this->assertWorkActivity($entity, $data['work_activity_id'] ?? null);

            $application = $entity->fertilizerApplications()->create([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'],
                'application_date' => $data['application_date'],
                'work_activity_id' => $data['work_activity_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => InventoryDocumentStatus::DRAFT,
            ]);

            $this->replaceItems($application, $entity, $data['items']);

            return $application->fresh(['items']) ?? $application;
        });
    }

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id: int,
     *     application_date: string,
     *     work_activity_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, dosage_per_plant?: float|int|string|null, plant_count?: int|null, notes?: string|null}>
     * }  $data
     */
    public function update(FertilizerApplication $application, array $data): FertilizerApplication
    {
        return DB::transaction(function () use ($application, $data) {
            $locked = FertilizerApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);

            $entity = $locked->plantationEntity;
            $this->assertLocation($entity, $data['plantation_id'], $data['plantation_block_id']);
            $this->assertWorkActivity($entity, $data['work_activity_id'] ?? null);

            $locked->update([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'],
                'application_date' => $data['application_date'],
                'work_activity_id' => $data['work_activity_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceItems($locked, $entity, $data['items']);

            return $locked->fresh(['items']) ?? $locked;
        });
    }

    public function post(FertilizerApplication $application): FertilizerApplication
    {
        return DB::transaction(function () use ($application) {
            $locked = FertilizerApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Pemupukan yang dibatalkan tidak dapat diposting ulang.');
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            $locked->load('items.inventoryItem');

            $existing = StockMovement::query()
                ->where('source_type', StockSourceType::FERTILIZER_APPLICATION)
                ->where('source_public_id', $locked->public_id)
                ->where('movement_type', StockMovementType::USAGE_OUT)
                ->exists();

            if (! $existing) {
                foreach ($locked->items->sortBy('inventory_item_id') as $line) {
                    $item = $this->stock->lockItem($line->inventoryItem);
                    $this->stock->record([
                        'plantation_entity_id' => $locked->plantation_entity_id,
                        'inventory_item_id' => $item->id,
                        'movement_type' => StockMovementType::USAGE_OUT,
                        'quantity' => $line->quantity,
                        'unit_cost' => $item->last_unit_cost,
                        'movement_date' => $locked->application_date->toDateString(),
                        'source_type' => StockSourceType::FERTILIZER_APPLICATION,
                        'source_public_id' => $locked->public_id,
                        'plantation_id' => $locked->plantation_id,
                        'plantation_block_id' => $locked->plantation_block_id,
                    ]);
                }
            }

            $locked->update(['status' => InventoryDocumentStatus::POSTED]);

            return $locked->fresh(['items']) ?? $locked;
        });
    }

    public function cancel(FertilizerApplication $application, string $reason = 'Pemupukan dibatalkan'): FertilizerApplication
    {
        return DB::transaction(function () use ($application, $reason) {
            $locked = FertilizerApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }

            if ($locked->isPosted()) {
                $locked->load('items.inventoryItem');

                $alreadyReversed = StockMovement::query()
                    ->where('source_type', StockSourceType::FERTILIZER_APPLICATION)
                    ->where('source_public_id', $locked->public_id)
                    ->where('movement_type', StockMovementType::RETURN_IN)
                    ->exists();

                if (! $alreadyReversed) {
                    foreach ($locked->items as $line) {
                        $this->stock->record([
                            'plantation_entity_id' => $locked->plantation_entity_id,
                            'inventory_item_id' => $line->inventory_item_id,
                            'movement_type' => StockMovementType::RETURN_IN,
                            'quantity' => $line->quantity,
                            'unit_cost' => $line->inventoryItem?->last_unit_cost,
                            'movement_date' => $locked->application_date->toDateString(),
                            'source_type' => StockSourceType::FERTILIZER_APPLICATION,
                            'source_public_id' => $locked->public_id,
                            'plantation_id' => $locked->plantation_id,
                            'plantation_block_id' => $locked->plantation_block_id,
                            'notes' => $reason,
                        ]);
                    }
                }
            }

            $locked->update([
                'status' => InventoryDocumentStatus::CANCELLED,
                'cancelled_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    public function deleteDraft(FertilizerApplication $application): void
    {
        if (! $application->isDraft()) {
            throw new InvalidArgumentException('Hanya pemupukan draft yang dapat dihapus.');
        }

        $application->items()->delete();
        $application->delete();
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: float|int|string, dosage_per_plant?: float|int|string|null, plant_count?: int|null, notes?: string|null}>  $items
     */
    private function replaceItems(FertilizerApplication $application, PlantationEntity $entity, array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Pemupukan harus memiliki minimal satu item.');
        }

        $application->items()->delete();

        foreach ($items as $line) {
            $item = InventoryItem::query()->forEntity($entity)->whereKey($line['inventory_item_id'])->first();
            if ($item === null) {
                throw new InvalidArgumentException('Item inventory tidak milik unit ini.');
            }

            if ($item->category !== InventoryCategory::Fertilizer) {
                throw new InvalidArgumentException('Pemupukan hanya boleh memakai item kategori pupuk.');
            }

            $quantity = Quantity::normalize($line['quantity']);
            if (! Quantity::isPositive($quantity)) {
                throw new InvalidArgumentException('Kuantitas harus lebih dari 0.');
            }

            $application->items()->create([
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'dosage_per_plant' => isset($line['dosage_per_plant']) && $line['dosage_per_plant'] !== null && $line['dosage_per_plant'] !== ''
                    ? Quantity::normalize($line['dosage_per_plant'])
                    : null,
                'plant_count' => $line['plant_count'] ?? null,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    private function assertDraft(FertilizerApplication $application): void
    {
        if (! $application->isDraft()) {
            throw new InvalidArgumentException('Pemupukan yang sudah diposting tidak dapat diubah.');
        }
    }

    private function assertLocation(PlantationEntity $entity, int $plantationId, int $blockId): void
    {
        $plantation = Plantation::query()->forEntity($entity)->whereKey($plantationId)->first();
        if ($plantation === null) {
            throw new InvalidArgumentException('Kebun tidak milik unit ini.');
        }

        $block = PlantationBlock::query()->whereKey($blockId)->first();
        if ($block === null || (int) $block->plantation_id !== (int) $plantation->id) {
            throw new InvalidArgumentException('Blok harus milik kebun yang dipilih.');
        }
    }

    private function assertWorkActivity(PlantationEntity $entity, ?int $activityId): void
    {
        if ($activityId === null) {
            return;
        }

        $activity = WorkActivity::query()->forEntity($entity)->whereKey($activityId)->first();
        if ($activity === null) {
            throw new InvalidArgumentException('Aktivitas kerja tidak milik unit ini.');
        }
    }
}
