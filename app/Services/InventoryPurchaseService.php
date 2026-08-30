<?php

namespace App\Services;

use App\Enums\InventoryDocumentStatus;
use App\Enums\RealizationSourceType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\BudgetAllocationItem;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryPurchaseItem;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryPurchaseService
{
    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly BudgetAllocationService $budgets,
        private readonly IntegrationOutboxService $outbox,
    ) {}

    /**
     * @param  array{
     *     supplier_id?: int|null,
     *     purchase_date: string,
     *     invoice_number?: string|null,
     *     description?: string|null,
     *     adjustment_amount?: float|int|string,
     *     budget_allocation_item_id?: int|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, unit_cost: float|int|string}>
     * }  $data
     */
    public function create(PlantationEntity $entity, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($entity, $data) {
            $purchase = $entity->inventoryPurchases()->create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'] ?? null,
                'adjustment_amount' => Money::normalize($data['adjustment_amount'] ?? '0'),
                'subtotal' => Money::normalize('0'),
                'total_amount' => Money::normalize('0'),
                'status' => InventoryDocumentStatus::DRAFT,
                'budget_allocation_item_id' => $data['budget_allocation_item_id'] ?? null,
            ]);

            $this->replaceItems($purchase, $entity, $data['items']);
            $this->assertSupplier($purchase, $entity);
            $this->recalculate($purchase);

            return $purchase->fresh(['items']) ?? $purchase;
        });
    }

    /**
     * @param  array{
     *     supplier_id?: int|null,
     *     purchase_date: string,
     *     invoice_number?: string|null,
     *     description?: string|null,
     *     adjustment_amount?: float|int|string,
     *     budget_allocation_item_id?: int|null,
     *     items: list<array{inventory_item_id: int, quantity: float|int|string, unit_cost: float|int|string}>
     * }  $data
     */
    public function update(InventoryPurchase $purchase, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $locked = InventoryPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $this->assertDraft($locked);

            $locked->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'description' => $data['description'] ?? null,
                'adjustment_amount' => Money::normalize($data['adjustment_amount'] ?? '0'),
                'budget_allocation_item_id' => $data['budget_allocation_item_id'] ?? null,
            ]);

            $entity = $locked->plantationEntity;
            $this->replaceItems($locked, $entity, $data['items']);
            $this->assertSupplier($locked, $entity);
            $this->recalculate($locked);

            return $locked->fresh(['items']) ?? $locked;
        });
    }

    public function post(InventoryPurchase $purchase): InventoryPurchase
    {
        return DB::transaction(function () use ($purchase) {
            $locked = InventoryPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw new InvalidArgumentException('Pembelian yang dibatalkan tidak dapat diposting ulang.');
            }

            if ($locked->isPosted()) {
                return $locked;
            }

            $locked->load('items.inventoryItem');
            $this->recalculate($locked);

            $existingIn = StockMovement::query()
                ->where('source_type', StockSourceType::INVENTORY_PURCHASE)
                ->where('source_public_id', $locked->public_id)
                ->where('movement_type', StockMovementType::PURCHASE_IN)
                ->exists();

            if (! $existingIn) {
                foreach ($locked->items as $line) {
                    $item = $this->stock->lockItem($line->inventoryItem);
                    $this->stock->record([
                        'plantation_entity_id' => $locked->plantation_entity_id,
                        'inventory_item_id' => $item->id,
                        'movement_type' => StockMovementType::PURCHASE_IN,
                        'quantity' => $line->quantity,
                        'unit_cost' => $line->unit_cost,
                        'movement_date' => $locked->purchase_date->toDateString(),
                        'source_type' => StockSourceType::INVENTORY_PURCHASE,
                        'source_public_id' => $locked->public_id,
                    ]);

                    $item->update(['last_unit_cost' => $line->unit_cost]);
                }
            }

            $realizationId = $locked->budget_realization_id;
            if ($locked->budget_allocation_item_id) {
                $budgetItem = BudgetAllocationItem::query()
                    ->whereKey($locked->budget_allocation_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertBudgetItem($locked, $budgetItem);

                $realization = $this->budgets->recordSourceRealization($budgetItem, [
                    'source_type' => RealizationSourceType::INVENTORY_PURCHASE,
                    'source_public_id' => $locked->public_id,
                    'amount' => $locked->total_amount,
                    'realization_date' => $locked->purchase_date->toDateString(),
                    'description' => sprintf('Pembelian inventory %s', $locked->invoice_number ?: $locked->public_id),
                ]);
                $realizationId = $realization->id;
            }

            $locked->update([
                'status' => InventoryDocumentStatus::POSTED,
                'budget_realization_id' => $realizationId,
            ]);

            $posted = $locked->fresh(['items.inventoryItem', 'supplier', 'plantationEntity']) ?? $locked;
            $this->outbox->recordPurchasePosted($posted);

            return $posted;
        });
    }

    public function cancel(InventoryPurchase $purchase, string $reason = 'Pembelian dibatalkan'): InventoryPurchase
    {
        return DB::transaction(function () use ($purchase, $reason) {
            $locked = InventoryPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                return $locked;
            }

            $wasPosted = $locked->isPosted();

            if ($wasPosted) {
                $locked->load('items.inventoryItem');

                $alreadyReversed = StockMovement::query()
                    ->where('source_type', StockSourceType::INVENTORY_PURCHASE)
                    ->where('source_public_id', $locked->public_id)
                    ->where('movement_type', StockMovementType::RETURN_OUT)
                    ->exists();

                if (! $alreadyReversed) {
                    foreach ($locked->items as $line) {
                        $this->stock->record([
                            'plantation_entity_id' => $locked->plantation_entity_id,
                            'inventory_item_id' => $line->inventory_item_id,
                            'movement_type' => StockMovementType::RETURN_OUT,
                            'quantity' => $line->quantity,
                            'unit_cost' => $line->unit_cost,
                            'movement_date' => $locked->purchase_date->toDateString(),
                            'source_type' => StockSourceType::INVENTORY_PURCHASE,
                            'source_public_id' => $locked->public_id,
                            'notes' => $reason,
                        ]);
                    }
                }

                if ($locked->budget_realization_id) {
                    $locked->loadMissing('budgetRealization');
                    $realization = $locked->budgetRealization;
                    if ($realization) {
                        $this->budgets->reverseRealization($realization, $reason);
                    }
                }
            }

            $locked->update([
                'status' => InventoryDocumentStatus::CANCELLED,
                'cancelled_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $cancelled = $locked->fresh(['plantationEntity']) ?? $locked;
            if ($wasPosted) {
                $this->outbox->recordPurchaseCancelled($cancelled);
            }

            return $cancelled;
        });
    }

    public function deleteDraft(InventoryPurchase $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new InvalidArgumentException('Hanya pembelian draft yang dapat dihapus.');
        }

        $purchase->items()->delete();
        $purchase->delete();
    }

    /**
     * @param  list<array{inventory_item_id: int, quantity: float|int|string, unit_cost: float|int|string}>  $items
     */
    private function replaceItems(InventoryPurchase $purchase, PlantationEntity $entity, array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Pembelian harus memiliki minimal satu item.');
        }

        $purchase->items()->delete();

        foreach ($items as $line) {
            $item = InventoryItem::query()
                ->forEntity($entity)
                ->whereKey($line['inventory_item_id'])
                ->first();

            if ($item === null) {
                throw new InvalidArgumentException('Item inventory tidak milik unit ini.');
            }

            $quantity = Quantity::normalize($line['quantity']);
            if (! Quantity::isPositive($quantity)) {
                throw new InvalidArgumentException('Kuantitas harus lebih dari 0.');
            }

            $unitCost = Money::normalize($line['unit_cost']);
            if (Money::cmp($unitCost, '0') === -1) {
                throw new InvalidArgumentException('Harga satuan tidak boleh negatif.');
            }

            $purchase->items()->create([
                'inventory_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => Money::lineTotal($quantity, $unitCost),
            ]);
        }
    }

    private function recalculate(InventoryPurchase $purchase): void
    {
        $purchase->load('items');

        $subtotal = Money::normalize('0');
        foreach ($purchase->items as $line) {
            $lineTotal = Money::lineTotal($line->quantity, $line->unit_cost);
            if ($line->line_total !== $lineTotal) {
                $line->update(['line_total' => $lineTotal]);
            }
            $subtotal = Money::add($subtotal, $lineTotal);
        }

        $adjustment = Money::normalize($purchase->adjustment_amount);
        $total = Money::add($subtotal, $adjustment);

        if (Money::cmp($total, '0') === -1) {
            throw new InvalidArgumentException('Total pembelian tidak boleh negatif.');
        }

        $purchase->update([
            'subtotal' => $subtotal,
            'total_amount' => $total,
        ]);
        $purchase->subtotal = $subtotal;
        $purchase->total_amount = $total;
    }

    private function assertDraft(InventoryPurchase $purchase): void
    {
        if (! $purchase->isDraft()) {
            throw new InvalidArgumentException('Pembelian yang sudah diposting tidak dapat diubah.');
        }
    }

    private function assertSupplier(InventoryPurchase $purchase, PlantationEntity $entity): void
    {
        if ($purchase->supplier_id === null) {
            return;
        }

        $supplier = Supplier::query()->forEntity($entity)->whereKey($purchase->supplier_id)->first();
        if ($supplier === null) {
            throw new InvalidArgumentException('Supplier tidak milik unit ini.');
        }
    }

    private function assertBudgetItem(InventoryPurchase $purchase, BudgetAllocationItem $item): void
    {
        $item->loadMissing('allocation');

        if ($item->allocation === null || (int) $item->allocation->plantation_entity_id !== (int) $purchase->plantation_entity_id) {
            throw new InvalidArgumentException('Item anggaran tidak milik unit ini.');
        }

        $expected = null;
        foreach ($purchase->items as $line) {
            $mapped = $line->inventoryItem->category->budgetCategory();
            if ($expected === null) {
                $expected = $mapped;
            } elseif ($expected !== $mapped) {
                throw new InvalidArgumentException('Pembelian dengan kategori barang campur tidak dapat memakai satu item anggaran.');
            }
        }

        if ($expected !== null && $item->category !== $expected) {
            throw new InvalidArgumentException('Kategori anggaran tidak sesuai dengan kategori barang.');
        }
    }
}
