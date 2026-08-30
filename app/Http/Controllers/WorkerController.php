<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\PlantationEntity;
use App\Models\Worker;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkerController extends Controller
{
    public function index(PlantationEntity $plantationEntity): View
    {
        $workers = Worker::query()
            ->forEntity($plantationEntity)
            ->orderBy('name')
            ->paginate(15);

        return view('workers.index', [
            'entity' => $plantationEntity,
            'workers' => $workers,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('workers.create', [
            'entity' => $plantationEntity,
        ]);
    }

    public function store(StoreWorkerRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $plantationEntity->workers()->create($request->validated());

        return redirect()
            ->route('plantation.workers.index', $plantationEntity)
            ->with('success', 'Pekerja berhasil ditambahkan.');
    }

    public function edit(PlantationEntity $plantationEntity, Worker $worker): View
    {
        EntityRouteBinder::assertOwnedBy($worker, $plantationEntity);

        return view('workers.edit', [
            'entity' => $plantationEntity,
            'worker' => $worker,
        ]);
    }

    public function show(PlantationEntity $plantationEntity, Worker $worker): View
    {
        EntityRouteBinder::assertOwnedBy($worker, $plantationEntity);

        $worker->load([
            'attendances.activity.workType',
            'attendances.payroll',
        ]);

        return view('workers.show', [
            'entity' => $plantationEntity,
            'worker' => $worker,
        ]);
    }

    public function update(
        UpdateWorkerRequest $request,
        PlantationEntity $plantationEntity,
        Worker $worker
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($worker, $plantationEntity);

        $worker->update($request->validated());

        return redirect()
            ->route('plantation.workers.index', $plantationEntity)
            ->with('success', 'Pekerja berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, Worker $worker): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($worker, $plantationEntity);

        $worker->delete();

        return redirect()
            ->route('plantation.workers.index', $plantationEntity)
            ->with('success', 'Pekerja berhasil dihapus.');
    }
}
