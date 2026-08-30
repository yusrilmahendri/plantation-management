<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(PlantationEntity $plantationEntity): View
    {
        $suppliers = Supplier::query()
            ->forEntity($plantationEntity)
            ->orderBy('name')
            ->paginate(15);

        return view('suppliers.index', [
            'entity' => $plantationEntity,
            'suppliers' => $suppliers,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('suppliers.create', [
            'entity' => $plantationEntity,
        ]);
    }

    public function store(StoreSupplierRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->suppliers()->create($request->validated());

        return redirect()
            ->route('plantation.suppliers.index', $plantationEntity)
            ->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function show(PlantationEntity $plantationEntity, Supplier $supplier): View
    {
        EntityRouteBinder::assertOwnedBy($supplier, $plantationEntity);

        $posted = $supplier->purchases()->where('status', \App\Enums\InventoryDocumentStatus::POSTED);

        return view('suppliers.show', [
            'entity' => $plantationEntity,
            'supplier' => $supplier,
            'purchases' => $supplier->purchases()->latest('purchase_date')->latest('id')->paginate(15),
            'purchaseCount' => (clone $posted)->count(),
            'purchaseTotal' => \App\Support\Money::normalize((clone $posted)->sum('total_amount')),
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, Supplier $supplier): View
    {
        EntityRouteBinder::assertOwnedBy($supplier, $plantationEntity);

        return view('suppliers.edit', [
            'entity' => $plantationEntity,
            'supplier' => $supplier,
        ]);
    }

    public function update(
        UpdateSupplierRequest $request,
        PlantationEntity $plantationEntity,
        Supplier $supplier
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($supplier, $plantationEntity);

        $supplier->update($request->validated());

        return redirect()
            ->route('plantation.suppliers.index', $plantationEntity)
            ->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, Supplier $supplier): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($supplier, $plantationEntity);

        $supplier->delete();

        return redirect()
            ->route('plantation.suppliers.index', $plantationEntity)
            ->with('success', 'Supplier berhasil dihapus.');
    }
}
