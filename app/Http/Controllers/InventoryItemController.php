<?php

namespace App\Http\Controllers;

use App\Enums\InventoryDocumentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryPurchaseItem;
use App\Models\PlantationEntity;
use App\Models\StockMovement;
use App\Services\InventoryStockService;
use App\Support\EntityRouteBinder;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function __construct(private readonly InventoryStockService $stock) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $items = InventoryItem::query()
            ->forEntity($plantationEntity)
            ->orderBy('name')
            ->paginate(15);

        $stocks = $this->stock->currentStocksFor($items->getCollection());
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $lowStockCount = InventoryItem::query()
            ->forEntity($plantationEntity)
            ->where('is_active', true)
            ->get()
            ->filter(fn (InventoryItem $item) => $this->stock->isLowStock($item))
            ->count();

        $purchaseValueThisMonth = InventoryPurchase::query()
            ->forEntity($plantationEntity)
            ->where('status', InventoryDocumentStatus::POSTED)
            ->whereBetween('purchase_date', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $usageQtyThisMonth = StockMovement::query()
            ->forEntity($plantationEntity)
            ->where('movement_type', StockMovementType::USAGE_OUT)
            ->where('source_type', StockSourceType::MATERIAL_USAGE)
            ->whereBetween('movement_date', [$monthStart, $monthEnd])
            ->sum('quantity');

        $fertilizerQtyThisMonth = StockMovement::query()
            ->forEntity($plantationEntity)
            ->where('movement_type', StockMovementType::USAGE_OUT)
            ->where('source_type', StockSourceType::FERTILIZER_APPLICATION)
            ->whereBetween('movement_date', [$monthStart, $monthEnd])
            ->sum('quantity');

        return view('inventory-items.index', [
            'entity' => $plantationEntity,
            'items' => $items,
            'stocks' => $stocks,
            'stockService' => $this->stock,
            'activeItemCount' => InventoryItem::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'lowStockCount' => $lowStockCount,
            'purchaseValueThisMonth' => Money::normalize($purchaseValueThisMonth),
            'usageQtyThisMonth' => Quantity::normalize($usageQtyThisMonth ?: '0'),
            'fertilizerQtyThisMonth' => Quantity::normalize($fertilizerQtyThisMonth ?: '0'),
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('inventory-items.create', [
            'entity' => $plantationEntity,
        ]);
    }

    public function store(StoreInventoryItemRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->inventoryItems()->create($request->validated());

        return redirect()
            ->route('plantation.inventory-items.index', $plantationEntity)
            ->with('success', 'Item inventory berhasil ditambahkan.');
    }

    public function show(PlantationEntity $plantationEntity, InventoryItem $inventoryItem): View
    {
        EntityRouteBinder::assertOwnedBy($inventoryItem, $plantationEntity);

        $movements = $inventoryItem->stockMovements()
            ->with(['plantation', 'block'])
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20);

        $lastPurchase = InventoryPurchaseItem::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->whereHas('purchase', fn ($query) => $query
                ->forEntity($plantationEntity)
                ->where('status', InventoryDocumentStatus::POSTED))
            ->with('purchase')
            ->latest('id')
            ->first();

        $lastUsage = StockMovement::query()
            ->where('inventory_item_id', $inventoryItem->id)
            ->where('movement_type', StockMovementType::USAGE_OUT)
            ->latest('movement_date')
            ->latest('id')
            ->first();

        $currentStock = $this->stock->currentStock($inventoryItem);

        return view('inventory-items.show', [
            'entity' => $plantationEntity,
            'item' => $inventoryItem,
            'currentStock' => $currentStock,
            'isLowStock' => $this->stock->isLowStock($inventoryItem, $currentStock),
            'movements' => $movements,
            'lastPurchase' => $lastPurchase?->purchase,
            'lastUsage' => $lastUsage,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, InventoryItem $inventoryItem): View
    {
        EntityRouteBinder::assertOwnedBy($inventoryItem, $plantationEntity);

        return view('inventory-items.edit', [
            'entity' => $plantationEntity,
            'item' => $inventoryItem,
        ]);
    }

    public function update(
        UpdateInventoryItemRequest $request,
        PlantationEntity $plantationEntity,
        InventoryItem $inventoryItem
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($inventoryItem, $plantationEntity);

        $inventoryItem->update($request->validated());

        return redirect()
            ->route('plantation.inventory-items.index', $plantationEntity)
            ->with('success', 'Item inventory berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, InventoryItem $inventoryItem): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($inventoryItem, $plantationEntity);

        if ($inventoryItem->hasOperationalHistory()) {
            $inventoryItem->update(['is_active' => false]);

            return redirect()
                ->route('plantation.inventory-items.index', $plantationEntity)
                ->with('success', 'Item inventory dinonaktifkan karena sudah memiliki histori.');
        }

        $inventoryItem->delete();

        return redirect()
            ->route('plantation.inventory-items.index', $plantationEntity)
            ->with('success', 'Item inventory berhasil dihapus.');
    }
}
