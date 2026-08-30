<?php

namespace App\Services;

use App\Enums\InventoryDocumentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\InventoryItem;
use App\Models\MaterialUsage;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaterialUsageService
{
    public function __construct(private readonly InventoryStockService $stock) {}

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id?: int|null,
     *     usage_date: string,
     *     description?: string|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, notes?: string|null}>
     * }  $data
     */
    public function create(PlantationEntity $entity, array $data): MaterialUsage
    {
        return DB::transaction(function () use ($entity, $data) {
            $this->assertLocation($entity, $data['plantation_id'], $data['plantation_block_id'] ?? null);

            $usage = $entity->materialUsages()->create([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'] ?? null,
                'usage_date' => $data['usage_date'],
                'description' => $data['description'] ?? null,
                'status' => InventoryDocumentStatus::DRAFT,
            ]);

            $this->replaceItems($usage, $entity, $data['items']);

            return $usage->fresh(['items']) ?? $usage;
        });
    }

    /**
     * @param  array{
     *     plantation_id: int,
     *     plantation_block_id?: int|null,
     *     usage_date: string,
     *     description?: string|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, notes?: string|null}>
     * }  $data
     */
    public function update(MaterialUsage $usage, array $data): MaterialUsage
    {
        return DB::transaction(function () use ($usage, $data) {
            $locked = MaterialUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);

            $entity = $locked->plantationEntity;
            $this->assertLocation($entity, $data['plantation_id'], $data['plantation_block_id'] ?? null);

            $locked->update([
                'plantation_id' => $data['plantation_id'],
                'plantation_block_id' => $data['plantation_block_id'] ?? null,
                'usage_date' => $data['usage_date'],
                'description' => $data['description'] ?? null,
            ]);

            $this->replaceItems($locked, $entity, $data['items']);

            return $locked->fresh(['items']) ?? $locked;
        });
    }

    public function post(MaterialUsage $usage): MaterialUsage
    {
        return DB::transaction(function () use ($usage) {
            $locked = MaterialUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Pemakaian yang dibatalkan tidak dapat diposting ulang.');
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            $locked->load('items.inventoryItem');

            $existing = StockMovement::query()
                ->where('source_type', StockSourceType::MATERIAL_USAGE)
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
                        'movement_date' => $locked->usage_date->toDateString(),
                        'source_type' => StockSourceType::MATERIAL_USAGE,
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

    public function cancel(MaterialUsage $usage, string $reason = 'Pemakaian dibatalkan'): MaterialUsage
    {
        return DB::transaction(function () use ($usage, $reason) {
            $locked = MaterialUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }

            if ($locked->isPosted()) {
                $locked->load('items.inventoryItem');

                $alreadyReversed = StockMovement::query()
                    ->where('source_type', StockSourceType::MATERIAL_USAGE)
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
                            'movement_date' => $locked->usage_date->toDateString(),
                            'source_type' => StockSourceType::MATERIAL_USAGE,
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

    public function deleteDraft(MaterialUsage $usage): void
    {
        if (! $usage->isDraft()) {
            throw new InvalidArgumentException('Hanya pemakaian draft yang dapat dihapus.');
        }

        $usage->items()->delete();
        $usage->delete();
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: float|int|string, notes?: string|null}>  $items
     */
    private function replaceItems(MaterialUsage $usage, PlantationEntity $entity, array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Pemakaian harus memiliki minimal satu item.');
        }

        $usage->items()->delete();

        foreach ($items as $line) {
            $item = InventoryItem::query()->forEntity($entity)->whereKey($line['inventory_item_id'])->first();
            if ($item === null) {
                throw new InvalidArgumentException('Item inventory tidak milik unit ini.');
            }

            $quantity = Quantity::normalize($line['quantity']);
            if (! Quantity::isPositive($quantity)) {
                throw new InvalidArgumentException('Kuantitas harus lebih dari 0.');
            }

            $usage->items()->create([
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    private function assertDraft(MaterialUsage $usage): void
    {
        if (! $usage->isDraft()) {
            throw new InvalidArgumentException('Pemakaian yang sudah diposting tidak dapat diubah.');
        }
    }

    private function assertLocation(PlantationEntity $entity, int $plantationId, ?int $blockId): void
    {
        $plantation = Plantation::query()->forEntity($entity)->whereKey($plantationId)->first();
        if ($plantation === null) {
            throw new InvalidArgumentException('Kebun tidak milik unit ini.');
        }

        if ($blockId === null) {
            return;
        }

        $block = PlantationBlock::query()->whereKey($blockId)->first();
        if ($block === null || (int) $block->plantation_id !== (int) $plantation->id) {
            throw new InvalidArgumentException('Blok harus milik kebun yang dipilih.');
        }
    }
}
