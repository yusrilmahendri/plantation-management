<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\ProductionDocumentStatus;
use App\Http\Requests\CancelInventoryDocumentRequest;
use App\Http\Requests\ReverseHarvestSalePaymentRequest;
use App\Http\Requests\StoreHarvestSalePaymentRequest;
use App\Http\Requests\StoreHarvestSaleRequest;
use App\Http\Requests\UpdateHarvestSaleRequest;
use App\Models\Buyer;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\HarvestSalePayment;
use App\Models\PlantationEntity;
use App\Services\HarvestAvailabilityService;
use App\Services\HarvestSalePaymentService;
use App\Services\HarvestSaleService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class HarvestSaleController extends Controller
{
    public function __construct(
        private readonly HarvestSaleService $sales,
        private readonly HarvestSalePaymentService $payments,
        private readonly HarvestAvailabilityService $availability,
    ) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $sales = HarvestSale::query()
            ->forEntity($plantationEntity)
            ->with('buyer')
            ->latest('sale_date')
            ->latest('id')
            ->paginate(15);

        return view('harvest-sales.index', [
            'entity' => $plantationEntity,
            'sales' => $sales,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('harvest-sales.create', [
            'entity' => $plantationEntity,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function store(StoreHarvestSaleRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        try {
            $sale = $this->sales->create($plantationEntity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.harvest-sales.show', [$plantationEntity, $sale])
            ->with('success', 'Penjualan draft dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, HarvestSale $harvestSale): View
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        $harvestSale->load(['buyer', 'items.harvest.plantation', 'items.harvest.block', 'payments']);

        return view('harvest-sales.show', [
            'entity' => $plantationEntity,
            'sale' => $harvestSale,
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, HarvestSale $harvestSale): View
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        if (! $harvestSale->isDraft()) {
            return redirect()
                ->route('plantation.harvest-sales.show', [$plantationEntity, $harvestSale])
                ->with('error', 'Penjualan yang sudah diposting tidak dapat diubah.');
        }

        $harvestSale->load('items.harvest');

        return view('harvest-sales.edit', [
            'entity' => $plantationEntity,
            'sale' => $harvestSale,
            ...$this->formData($plantationEntity),
        ]);
    }

    public function update(UpdateHarvestSaleRequest $request, PlantationEntity $plantationEntity, HarvestSale $harvestSale): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        try {
            $this->sales->update($harvestSale, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.harvest-sales.show', [$plantationEntity, $harvestSale])
            ->with('success', 'Penjualan diperbarui.');
    }

    public function post(PlantationEntity $plantationEntity, HarvestSale $harvestSale): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        try {
            $this->sales->post($harvestSale);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvest-sales.show', [$plantationEntity, $harvestSale])
            ->with('success', 'Penjualan diposting.');
    }

    public function cancel(CancelInventoryDocumentRequest $request, PlantationEntity $plantationEntity, HarvestSale $harvestSale): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        try {
            $this->sales->cancel($harvestSale, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvest-sales.show', [$plantationEntity, $harvestSale])
            ->with('success', 'Penjualan dibatalkan.');
    }

    public function destroy(PlantationEntity $plantationEntity, HarvestSale $harvestSale): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        try {
            $this->sales->deleteDraft($harvestSale);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.harvest-sales.index', $plantationEntity)
            ->with('success', 'Penjualan draft dihapus.');
    }

    public function storePayment(
        StoreHarvestSalePaymentRequest $request,
        PlantationEntity $plantationEntity,
        HarvestSale $harvestSale,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);

        try {
            $this->payments->record($harvestSale, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function reversePayment(
        ReverseHarvestSalePaymentRequest $request,
        PlantationEntity $plantationEntity,
        HarvestSale $harvestSale,
        HarvestSalePayment $harvestSalePayment,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($harvestSale, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($harvestSalePayment, $plantationEntity);

        if ((int) $harvestSalePayment->harvest_sale_id !== (int) $harvestSale->id) {
            abort(404);
        }

        try {
            $this->payments->reverse($harvestSalePayment, $request->validated('reason'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran dibatalkan.');
    }

    /**
     * @return array{buyers: \Illuminate\Support\Collection, harvestOptions: list<array<string, mixed>>}
     */
    private function formData(PlantationEntity $entity): array
    {
        $harvests = Harvest::query()
            ->forEntity($entity)
            ->where('status', ProductionDocumentStatus::POSTED)
            ->with(['plantation', 'block'])
            ->latest('harvest_date')
            ->get();

        return [
            'buyers' => Buyer::query()->forEntity($entity)->where('is_active', true)->orderBy('name')->get(),
            'harvestOptions' => $harvests->map(fn (Harvest $harvest) => [
                'public_id' => $harvest->public_id,
                'label' => sprintf(
                    '%s · %s · %s · tersedia %s %s',
                    $harvest->harvest_date->format('d/m/Y'),
                    $harvest->plantation->name.($harvest->block ? ' / '.$harvest->block->code : ''),
                    $harvest->commodity->label(),
                    \App\Support\Quantity::format($this->availability->availableQuantity($harvest)),
                    $harvest->unit
                ),
            ])->all(),
        ];
    }
}
