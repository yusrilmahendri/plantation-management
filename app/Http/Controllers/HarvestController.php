<?php

namespace App\Http\Controllers;

use App\Enums\Commodity;
use App\Http\Requests\CancelInventoryDocumentRequest;
use App\Http\Requests\StoreHarvestRequest;
use App\Http\Requests\UpdateHarvestRequest;
use App\Models\Harvest;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Services\HarvestAvailabilityService;
use App\Services\HarvestService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class HarvestController extends Controller
{
    public function __construct(
        private readonly HarvestService $harvests,
        private readonly HarvestAvailabilityService $availability,
    ) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $harvests = Harvest::query()
            ->forEntity($plantationEntity)
            ->with(['plantation', 'block'])
            ->latest('harvest_date')
            ->latest('id')
            ->paginate(15);

        return view('harvests.index', [
            'entity' => $plantationEntity,
            'harvests' => $harvests,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        $activity = null;
        if (request()->filled('work_activity_public_id')) {
            $activity = WorkActivity::query()
                ->forEntity($plantationEntity)
                ->where('public_id', request('work_activity_public_id'))
                ->with(['plantation', 'block'])
                ->first();
        }

        return view('harvests.create', [
            'entity' => $plantationEntity,
            'activity' => $activity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function store(StoreHarvestRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $harvest = $this->harvests->create($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.harvests.show', [$plantationEntity, $harvest])
            ->with('success', 'Panen draft dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, Harvest $harvest): View
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        $harvest->load(['plantation', 'block', 'workActivity', 'saleItems.sale.buyer']);
        $sold = $this->availability->soldQuantity($harvest);
        $available = $this->availability->availableQuantity($harvest);

        $laborCost = null;
        $costNote = null;
        if ($harvest->workActivity) {
            $laborCost = $this->harvests->activityLaborCost($harvest->workActivity);
            $costNote = 'Biaya upah adalah total payroll posted aktivitas ini, bukan alokasi per panen.';
        }

        return view('harvests.show', [
            'entity' => $plantationEntity,
            'harvest' => $harvest,
            'sold' => $sold,
            'available' => $available,
            'laborCost' => $laborCost,
            'costNote' => $costNote,
            'costPerUnit' => $laborCost && $harvest->isPosted() && \App\Support\Quantity::cmp($harvest->quantity, '0') === 1
                ? bcdiv($laborCost, \App\Support\Quantity::normalize($harvest->quantity), 2)
                : null,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, Harvest $harvest): View
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        if (! $harvest->isDraft()) {
            return redirect()
                ->route('plantation.harvests.show', [$plantationEntity, $harvest])
                ->with('error', 'Panen yang sudah diposting tidak dapat diubah.');
        }

        return view('harvests.edit', [
            'entity' => $plantationEntity,
            'harvest' => $harvest,
            'activity' => $harvest->workActivity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function update(UpdateHarvestRequest $request, PlantationEntity $plantationEntity, Harvest $harvest): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        try {
            $this->harvests->update($harvest, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.harvests.show', [$plantationEntity, $harvest])
            ->with('success', 'Panen diperbarui.');
    }

    public function post(PlantationEntity $plantationEntity, Harvest $harvest): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        try {
            $this->harvests->post($harvest);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvests.show', [$plantationEntity, $harvest])
            ->with('success', 'Panen diposting.');
    }

    public function cancel(CancelInventoryDocumentRequest $request, PlantationEntity $plantationEntity, Harvest $harvest): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        try {
            $this->harvests->cancel($harvest, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvests.show', [$plantationEntity, $harvest])
            ->with('success', 'Panen dibatalkan.');
    }

    public function destroy(PlantationEntity $plantationEntity, Harvest $harvest): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvest, $plantationEntity);

        try {
            $this->harvests->deleteDraft($harvest);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvests.index', $plantationEntity)
            ->with('success', 'Panen draft dihapus.');
    }

    /**
     * @return array{plantations: \Illuminate\Support\Collection, blocks: list<array{public_id: string, plantation_public_id: string, label: string}>, activities: \Illuminate\Support\Collection, commodities: array<int, Commodity>}
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
            'activities' => WorkActivity::query()->forEntity($entity)->latest('activity_date')->limit(50)->get(),
            'commodities' => Commodity::cases(),
        ];
    }
}
