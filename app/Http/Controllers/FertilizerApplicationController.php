<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCategory;
use App\Http\Requests\CancelInventoryDocumentRequest;
use App\Http\Requests\StoreFertilizerApplicationRequest;
use App\Http\Requests\UpdateFertilizerApplicationRequest;
use App\Models\FertilizerApplication;
use App\Models\InventoryItem;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Services\FertilizerApplicationService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class FertilizerApplicationController extends Controller
{
    public function __construct(private readonly FertilizerApplicationService $applications) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $applications = FertilizerApplication::query()
            ->forEntity($plantationEntity)
            ->with(['plantation', 'block'])
            ->latest('application_date')
            ->latest('id')
            ->paginate(15);

        return view('fertilizer-applications.index', [
            'entity' => $plantationEntity,
            'applications' => $applications,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('fertilizer-applications.create', [
            'entity' => $plantationEntity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function store(StoreFertilizerApplicationRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $application = $this->applications->create($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.fertilizer-applications.show', [$plantationEntity, $application])
            ->with('success', 'Pemupukan draft dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, FertilizerApplication $fertilizerApplication): View
    {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        $fertilizerApplication->load(['plantation', 'block', 'workActivity', 'items.inventoryItem']);

        return view('fertilizer-applications.show', [
            'entity' => $plantationEntity,
            'application' => $fertilizerApplication,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, FertilizerApplication $fertilizerApplication): View
    {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        if (! $fertilizerApplication->isDraft()) {
            return redirect()
                ->route('plantation.fertilizer-applications.show', [$plantationEntity, $fertilizerApplication])
                ->with('error', 'Pemupukan yang sudah diposting tidak dapat diubah.');
        }

        $fertilizerApplication->load('items.inventoryItem');

        return view('fertilizer-applications.edit', [
            'entity' => $plantationEntity,
            'application' => $fertilizerApplication,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function update(
        UpdateFertilizerApplicationRequest $request,
        PlantationEntity $plantationEntity,
        FertilizerApplication $fertilizerApplication,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        try {
            $this->applications->update($fertilizerApplication, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.fertilizer-applications.show', [$plantationEntity, $fertilizerApplication])
            ->with('success', 'Pemupukan diperbarui.');
    }

    public function post(PlantationEntity $plantationEntity, FertilizerApplication $fertilizerApplication): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        try {
            $this->applications->post($fertilizerApplication);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.fertilizer-applications.show', [$plantationEntity, $fertilizerApplication])
            ->with('success', 'Pemupukan diposting.');
    }

    public function cancel(
        CancelInventoryDocumentRequest $request,
        PlantationEntity $plantationEntity,
        FertilizerApplication $fertilizerApplication,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        try {
            $this->applications->cancel($fertilizerApplication, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.fertilizer-applications.show', [$plantationEntity, $fertilizerApplication])
            ->with('success', 'Pemupukan dibatalkan.');
    }

    public function destroy(PlantationEntity $plantationEntity, FertilizerApplication $fertilizerApplication): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($fertilizerApplication, $plantationEntity);

        try {
            $this->applications->deleteDraft($fertilizerApplication);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.fertilizer-applications.index', $plantationEntity)
            ->with('success', 'Pemupukan draft dihapus.');
    }

    /**
     * @return array{plantations: \Illuminate\Support\Collection, blocks: list<array{public_id: string, plantation_public_id: string, label: string}>, inventoryItems: \Illuminate\Support\Collection, activities: \Illuminate\Support\Collection}
     */
    private function formData(PlantationEntity $entity): array
    {
        return [
            'plantations' => Plantation::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
            'blocks' => Plantation::query()
                ->forEntity($entity)
                ->with(['blocks' => fn ($query) => $query->orderBy('code')])
                ->get()
                ->flatMap(function (Plantation $plantation) {
                    return $plantation->blocks->map(fn ($block) => [
                        'public_id' => $block->public_id,
                        'plantation_public_id' => $plantation->public_id,
                        'label' => $block->code.($block->name ? ' — '.$block->name : ''),
                    ]);
                })
                ->values()
                ->all(),
            'inventoryItems' => InventoryItem::query()
                ->forEntity($entity)
                ->where('category', InventoryCategory::Fertilizer)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'activities' => WorkActivity::query()->forEntity($entity)->latest('activity_date')->limit(50)->get(),
        ];
    }
}
