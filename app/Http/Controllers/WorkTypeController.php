<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkTypeRequest;
use App\Http\Requests\UpdateWorkTypeRequest;
use App\Models\PlantationEntity;
use App\Models\WorkType;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkTypeController extends Controller
{
    public function index(PlantationEntity $plantationEntity): View
    {
        $workTypes = WorkType::query()
            ->forEntity($plantationEntity)
            ->orderBy('name')
            ->paginate(15);

        return view('work-types.index', [
            'entity' => $plantationEntity,
            'workTypes' => $workTypes,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('work-types.create', [
            'entity' => $plantationEntity,
        ]);
    }

    public function store(StoreWorkTypeRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->workTypes()->create($request->validated());

        return redirect()
            ->route('plantation.work-types.index', $plantationEntity)
            ->with('success', 'Jenis pekerjaan berhasil ditambahkan.');
    }

    public function edit(PlantationEntity $plantationEntity, WorkType $workType): View
    {
        EntityRouteBinder::assertOwnedBy($workType, $plantationEntity);

        return view('work-types.edit', [
            'entity' => $plantationEntity,
            'workType' => $workType,
        ]);
    }

    public function update(
        UpdateWorkTypeRequest $request,
        PlantationEntity $plantationEntity,
        WorkType $workType
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workType, $plantationEntity);

        $workType->update($request->validated());

        return redirect()
            ->route('plantation.work-types.index', $plantationEntity)
            ->with('success', 'Jenis pekerjaan berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, WorkType $workType): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($workType, $plantationEntity);

        $workType->delete();

        return redirect()
            ->route('plantation.work-types.index', $plantationEntity)
            ->with('success', 'Jenis pekerjaan berhasil dihapus.');
    }
}
