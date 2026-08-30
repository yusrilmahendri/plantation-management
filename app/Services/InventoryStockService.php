<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\InventoryItem;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryStockService
{
    public function currentStock(InventoryItem $item): string
    {
        $inbound = StockMovement::query()
            ->where('inventory_item_id', $item->id)
            ->whereIn('movement_type', StockMovementType::inboundValues())
            ->sum('quantity');

        $outbound = StockMovement::query()
            ->where('inventory_item_id', $item->id)
            ->whereIn('movement_type', StockMovementType::outboundValues())
            ->sum('quantity');

        return Quantity::sub($inbound ?: '0', $outbound ?: '0');
    }

    /**
     * @param  iterable<int, InventoryItem>  $items
     * @return array<int, string>
     */
    public function currentStocksFor(iterable $items): array
    {
        $ids = Collection::make($items)->pluck('id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $rows = StockMovement::query()
            ->whereIn('inventory_item_id', $ids)
            ->selectRaw('inventory_item_id, movement_type, SUM(quantity) as qty')
            ->groupBy('inventory_item_id', 'movement_type')
            ->get();

        $stocks = [];
        foreach ($ids as $id) {
            $stocks[(int) $id] = Quantity::normalize('0');
        }

        foreach ($rows as $row) {
            $id = (int) $row->inventory_item_id;
            $type = $row->movement_type instanceof StockMovementType
                ? $row->movement_type
                : StockMovementType::from((string) $row->movement_type);
            $qty = Quantity::normalize($row->qty);

            $stocks[$id] = $type->isInbound()
                ? Quantity::add($stocks[$id], $qty)
                : Quantity::sub($stocks[$id], $qty);
        }

        return $stocks;
    }

    public function isLowStock(InventoryItem $item, ?string $currentStock = null): bool
    {
        if ($item->minimum_stock === null) {
            return false;
        }

        $stock = $currentStock ?? $this->currentStock($item);

        return Quantity::cmp($stock, $item->minimum_stock) <= 0;
    }

    public function lockItem(InventoryItem $item): InventoryItem
    {
        return InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array{
     *     plantation_entity_id: int,
     *     inventory_item_id: int,
     *     movement_type: StockMovementType|string,
     *     quantity: float|int|string,
     *     unit_cost?: float|int|string|null,
     *     movement_date: string,
     *     source_type: StockSourceType|string,
     *     source_public_id: string,
     *     plantation_id?: int|null,
     *     plantation_block_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function record(array $data): StockMovement
    {
        $type = $data['movement_type'] instanceof StockMovementType
            ? $data['movement_type']
            : StockMovementType::from((string) $data['movement_type']);

        $quantity = Quantity::normalize($data['quantity']);

        if (! Quantity::isPositive($quantity)) {
            throw new InvalidArgumentException('Kuantitas pergerakan stok harus lebih dari 0.');
        }

        $item = $this->lockItem(InventoryItem::query()->findOrFail($data['inventory_item_id']));

        if ((int) $item->plantation_entity_id !== (int) $data['plantation_entity_id']) {
            throw new InvalidArgumentException('Item inventory tidak milik unit ini.');
        }

        if (! $type->isInbound()) {
            $available = $this->currentStock($item);
            if (Quantity::cmp($available, $quantity) === -1) {
                throw new InvalidArgumentException('Stok tidak mencukupi untuk item '.$item->name.'.');
            }
        }

        $sourceType = $data['source_type'] instanceof StockSourceType
            ? $data['source_type']
            : StockSourceType::from((string) $data['source_type']);

        return StockMovement::query()->create([
            'plantation_entity_id' => $data['plantation_entity_id'],
            'inventory_item_id' => $item->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $data['unit_cost'] ?? null,
            'movement_date' => $data['movement_date'],
            'source_type' => $sourceType,
            'source_public_id' => $data['source_public_id'],
            'plantation_id' => $data['plantation_id'] ?? null,
            'plantation_block_id' => $data['plantation_block_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array{
     *     inventory_item_id: int,
     *     movement_type: StockMovementType|string,
     *     quantity: float|int|string,
     *     movement_date: string,
     *     reason: string
     * }  $data
     */
    public function adjust(PlantationEntity $entity, array $data): StockMovement
    {
        $type = $data['movement_type'] instanceof StockMovementType
            ? $data['movement_type']
            : StockMovementType::from((string) $data['movement_type']);

        if (! in_array($type, [StockMovementType::ADJUSTMENT_IN, StockMovementType::ADJUSTMENT_OUT], true)) {
            throw new InvalidArgumentException('Jenis penyesuaian stok tidak valid.');
        }

        return $this->record([
            'plantation_entity_id' => $entity->id,
            'inventory_item_id' => $data['inventory_item_id'],
            'movement_type' => $type,
            'quantity' => $data['quantity'],
            'movement_date' => $data['movement_date'],
            'source_type' => StockSourceType::STOCK_ADJUSTMENT,
            'source_public_id' => (string) Str::ulid(),
            'notes' => $data['reason'],
        ]);
    }
}
