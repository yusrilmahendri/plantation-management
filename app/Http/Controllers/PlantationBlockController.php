<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlantationBlockRequest;
use App\Http\Requests\UpdatePlantationBlockRequest;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Services\ProductionReportService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlantationBlockController extends Controller
{
    public function __construct(private readonly ProductionReportService $reports) {}
    public function index(PlantationEntity $plantationEntity): View
    {
        $blocks = PlantationBlock::query()
            ->forEntity($plantationEntity)
            ->with('plantation')
            ->orderBy('code')
            ->paginate(15);

        return view('blocks.index', [
            'entity' => $plantationEntity,
            'blocks' => $blocks,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('blocks.create', [
            'entity' => $plantationEntity,
            'plantations' => $this->plantations($plantationEntity),
        ]);
    }

    public function store(StorePlantationBlockRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $data = $request->validated();
        unset($data['plantation_public_id']);
        $data['plantation_id'] = $request->plantationId();

        PlantationBlock::query()->create($data);

        return redirect()
            ->route('plantation.blocks.index', $plantationEntity)
            ->with('success', 'Blok berhasil ditambahkan.');
    }

    public function show(PlantationEntity $plantationEntity, PlantationBlock $plantationBlock): View
    {
        EntityRouteBinder::assertOwnedBy($plantationBlock, $plantationEntity);

        $plantationBlock->load('plantation');

        return view('blocks.show', [
            'entity' => $plantationEntity,
            'block' => $plantationBlock,
            'metrics' => $this->reports->blockMetrics($plantationBlock),
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, PlantationBlock $plantationBlock): View
    {
        EntityRouteBinder::assertOwnedBy($plantationBlock, $plantationEntity);

        return view('blocks.edit', [
            'entity' => $plantationEntity,
            'block' => $plantationBlock,
            'plantations' => $this->plantations($plantationEntity),
        ]);
    }

    public function update(
        UpdatePlantationBlockRequest $request,
        PlantationEntity $plantationEntity,
        PlantationBlock $plantationBlock
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($plantationBlock, $plantationEntity);

        $data = $request->validated();
        unset($data['plantation_public_id']);
        $data['plantation_id'] = $request->plantationId();

        $plantationBlock->update($data);

        return redirect()
            ->route('plantation.blocks.index', $plantationEntity)
            ->with('success', 'Blok berhasil diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, PlantationBlock $plantationBlock): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($plantationBlock, $plantationEntity);

        $plantationBlock->delete();

        return redirect()
            ->route('plantation.blocks.index', $plantationEntity)
            ->with('success', 'Blok berhasil dihapus.');
    }

    private function plantations(PlantationEntity $entity)
    {
        return Plantation::query()
            ->forEntity($entity)
            ->orderBy('name')
            ->get();
    }
}
