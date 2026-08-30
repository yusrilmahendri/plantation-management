<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelInventoryDocumentRequest;
use App\Http\Requests\StoreInventoryPurchaseRequest;
use App\Http\Requests\UpdateInventoryPurchaseRequest;
use App\Models\BudgetAllocationItem;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Services\InventoryPurchaseService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class InventoryPurchaseController extends Controller
{
    public function __construct(private readonly InventoryPurchaseService $purchases) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $purchases = InventoryPurchase::query()
            ->forEntity($plantationEntity)
            ->with('supplier')
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(15);

        return view('purchases.index', [
            'entity' => $plantationEntity,
            'purchases' => $purchases,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('purchases.create', [
            'entity' => $plantationEntity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function store(StoreInventoryPurchaseRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $purchase = $this->purchases->create($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.purchases.show', [$plantationEntity, $purchase])
            ->with('success', 'Pembelian draft dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, InventoryPurchase $inventoryPurchase): View
    {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        $inventoryPurchase->load(['supplier', 'items.inventoryItem', 'budgetAllocationItem', 'budgetRealization']);

        return view('purchases.show', [
            'entity' => $plantationEntity,
            'purchase' => $inventoryPurchase,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, InventoryPurchase $inventoryPurchase): View
    {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        if (! $inventoryPurchase->isDraft()) {
            return redirect()
                ->route('plantation.purchases.show', [$plantationEntity, $inventoryPurchase])
                ->with('error', 'Pembelian yang sudah diposting tidak dapat diubah.');
        }

        $inventoryPurchase->load('items.inventoryItem');

        return view('purchases.edit', [
            'entity' => $plantationEntity,
            'purchase' => $inventoryPurchase,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function update(
        UpdateInventoryPurchaseRequest $request,
        PlantationEntity $plantationEntity,
        InventoryPurchase $inventoryPurchase,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        try {
            $this->purchases->update($inventoryPurchase, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.purchases.show', [$plantationEntity, $inventoryPurchase])
            ->with('success', 'Pembelian diperbarui.');
    }

    public function post(PlantationEntity $plantationEntity, InventoryPurchase $inventoryPurchase): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        try {
            $this->purchases->post($inventoryPurchase);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.purchases.show', [$plantationEntity, $inventoryPurchase])
            ->with('success', 'Pembelian diposting.');
    }

    public function cancel(
        CancelInventoryDocumentRequest $request,
        PlantationEntity $plantationEntity,
        InventoryPurchase $inventoryPurchase,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        try {
            $this->purchases->cancel($inventoryPurchase, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.purchases.show', [$plantationEntity, $inventoryPurchase])
            ->with('success', 'Pembelian dibatalkan.');
    }

    public function destroy(PlantationEntity $plantationEntity, InventoryPurchase $inventoryPurchase): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($inventoryPurchase, $plantationEntity);

        try {
            $this->purchases->deleteDraft($inventoryPurchase);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.purchases.index', $plantationEntity)
            ->with('success', 'Pembelian draft dihapus.');
    }

    /**
     * @return array{suppliers: \Illuminate\Support\Collection, inventoryItems: \Illuminate\Support\Collection, budgetItems: \Illuminate\Support\Collection}
     */
    private function formData(PlantationEntity $entity): array
    {
        return [
            'suppliers' => Supplier::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
            'inventoryItems' => InventoryItem::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
            'budgetItems' => BudgetAllocationItem::query()
                ->whereHas('allocation', fn ($query) => $query->forEntity($entity))
                ->with('allocation')
                ->orderBy('name')
                ->get(),
        ];
    }
}
