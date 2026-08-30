<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\InventoryItem;
use App\Models\PlantationEntity;
use App\Services\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryStockService $stock) {}

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('stock-adjustments.create', [
            'entity' => $plantationEntity,
            'items' => InventoryItem::query()->forEntity($plantationEntity)->where('is_active', true)->orderBy('name')->get(),
            'types' => [
                StockMovementType::ADJUSTMENT_IN,
                StockMovementType::ADJUSTMENT_OUT,
            ],
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $this->stock->adjust($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.inventory-items.index', $plantationEntity)
            ->with('success', 'Penyesuaian stok dicatat.');
    }
}
