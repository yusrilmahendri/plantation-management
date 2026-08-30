<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantationRequest;
use App\Http\Requests\UpdatePlantationRequest;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlantationController extends Controller
{
    public function index(PlantationEntity $plantationEntity): View
    {
        $plantations = Plantation::query()
            ->forEntity($plantationEntity)
            ->orderBy('name')
            ->paginate(15);

        return view('plantations.index', [
            'entity' => $plantationEntity,
            'plantations' => $plantations,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('plantations.create', [
            'entity' => $plantationEntity,
        ]);
    }

    public function store(StorePlantationRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->plantations()->create($request->validated());

        return redirect()
            ->route('plantation.plantations.index', $plantationEntity)
            ->with('success', 'Kebun berhasil ditambahkan.');
    }

    public function edit(PlantationEntity $plantationEntity, Plantation $plantation): View
    {
        EntityRouteBinder::assertOwnedBy($plantation, $plantationEntity);

        return view('plantations.edit', [
            'entity' => $plantationEntity,
            'plantation' => $plantation,
        ]);
    }

    public function update(
        UpdatePlantationRequest $request,
        PlantationEntity $plantationEntity,
        Plantation $plantation
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($plantation, $plantationEntity);

        $plantation->update($request->validated());

        return redirect()
            ->route('plantation.plantations.index', $plantationEntity)
            ->with('success', 'Kebun berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, Plantation $plantation): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($plantation, $plantationEntity);

        if ($plantation->blocks()->exists()) {
            return redirect()
                ->route('plantation.plantations.index', $plantationEntity)
                ->with('error', 'Kebun tidak dapat dihapus karena masih memiliki blok.');
        }

        $plantation->delete();

        return redirect()
            ->route('plantation.plantations.index', $plantationEntity)
            ->with('success', 'Kebun berhasil dihapus.');
    }
}
