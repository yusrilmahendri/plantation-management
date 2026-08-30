<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelInventoryDocumentRequest;
use App\Http\Requests\StoreMaterialUsageRequest;
use App\Http\Requests\UpdateMaterialUsageRequest;
use App\Models\InventoryItem;
use App\Models\MaterialUsage;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Services\MaterialUsageService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class MaterialUsageController extends Controller
{
    public function __construct(private readonly MaterialUsageService $usages) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $usages = MaterialUsage::query()
            ->forEntity($plantationEntity)
            ->with(['plantation', 'block'])
            ->latest('usage_date')
            ->latest('id')
            ->paginate(15);

        return view('material-usages.index', [
            'entity' => $plantationEntity,
            'usages' => $usages,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('material-usages.create', [
            'entity' => $plantationEntity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function store(StoreMaterialUsageRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $usage = $this->usages->create($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.material-usages.show', [$plantationEntity, $usage])
            ->with('success', 'Pemakaian draft dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, MaterialUsage $materialUsage): View
    {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        $materialUsage->load(['plantation', 'block', 'items.inventoryItem']);

        return view('material-usages.show', [
            'entity' => $plantationEntity,
            'usage' => $materialUsage,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, MaterialUsage $materialUsage): View
    {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        if (! $materialUsage->isDraft()) {
            return redirect()
                ->route('plantation.material-usages.show', [$plantationEntity, $materialUsage])
                ->with('error', 'Pemakaian yang sudah diposting tidak dapat diubah.');
        }

        $materialUsage->load('items.inventoryItem');

        return view('material-usages.edit', [
            'entity' => $plantationEntity,
            'usage' => $materialUsage,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function update(
        UpdateMaterialUsageRequest $request,
        PlantationEntity $plantationEntity,
        MaterialUsage $materialUsage,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        try {
            $this->usages->update($materialUsage, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.material-usages.show', [$plantationEntity, $materialUsage])
            ->with('success', 'Pemakaian diperbarui.');
    }

    public function post(PlantationEntity $plantationEntity, MaterialUsage $materialUsage): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        try {
            $this->usages->post($materialUsage);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.material-usages.show', [$plantationEntity, $materialUsage])
            ->with('success', 'Pemakaian diposting.');
    }

    public function cancel(
        CancelInventoryDocumentRequest $request,
        PlantationEntity $plantationEntity,
        MaterialUsage $materialUsage,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        try {
            $this->usages->cancel($materialUsage, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.material-usages.show', [$plantationEntity, $materialUsage])
            ->with('success', 'Pemakaian dibatalkan.');
    }

    public function destroy(PlantationEntity $plantationEntity, MaterialUsage $materialUsage): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($materialUsage, $plantationEntity);

        try {
            $this->usages->deleteDraft($materialUsage);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.material-usages.index', $plantationEntity)
            ->with('success', 'Pemakaian draft dihapus.');
    }

    /**
     * @return array{plantations: \Illuminate\Support\Collection, blocks: list<array{public_id: string, plantation_public_id: string, label: string}>, inventoryItems: \Illuminate\Support\Collection}
     */
    private function formData(PlantationEntity $entity): array
    {
        return [
            'plantations' => Plantation::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
            'blocks' => $this->blocksPayload($entity),
            'inventoryItems' => InventoryItem::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * @return list<array{public_id: string, plantation_public_id: string, label: string}>
     */
    private function blocksPayload(PlantationEntity $entity): array
    {
        return Plantation::query()
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
            ->all();
    }
}
