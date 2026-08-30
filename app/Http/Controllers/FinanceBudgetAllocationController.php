<?php

namespace App\Http\Controllers;

use App\Enums\BudgetItemCategory;
use App\Enums\RealizationSourceType;
use App\Http\Requests\StoreBudgetAllocationItemRequest;
use App\Http\Requests\StoreBudgetRealizationRequest;
use App\Models\BudgetAllocationItem;
use App\Models\BudgetRealization;
use App\Models\FinanceBudgetAllocation;
use App\Models\PlantationEntity;
use App\Services\BudgetAllocationService;
use App\Support\EntityRouteBinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class FinanceBudgetAllocationController extends Controller
{
    public function __construct(private readonly BudgetAllocationService $allocations) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $budgets = FinanceBudgetAllocation::query()
            ->forEntity($plantationEntity)
            ->with('items')
            ->latest('period_start')
            ->paginate(15);

        return view('budgets.index', [
            'entity' => $plantationEntity,
            'budgets' => $budgets,
        ]);
    }

    public function show(PlantationEntity $plantationEntity, FinanceBudgetAllocation $financeBudgetAllocation): View
    {
        EntityRouteBinder::assertOwnedBy($financeBudgetAllocation, $plantationEntity);

        $financeBudgetAllocation->load(['items.realizations']);

        return view('budgets.show', [
            'entity' => $plantationEntity,
            'allocation' => $financeBudgetAllocation,
            'categories' => BudgetItemCategory::cases(),
        ]);
    }

    public function storeItem(
        StoreBudgetAllocationItemRequest $request,
        PlantationEntity $plantationEntity,
        FinanceBudgetAllocation $financeBudgetAllocation,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($financeBudgetAllocation, $plantationEntity);

        try {
            $this->allocations->addItem($financeBudgetAllocation, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.budgets.show', [$plantationEntity, $financeBudgetAllocation])
            ->with('success', 'Item alokasi ditambahkan.');
    }

    public function destroyItem(
        PlantationEntity $plantationEntity,
        FinanceBudgetAllocation $financeBudgetAllocation,
        BudgetAllocationItem $budgetAllocationItem,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($financeBudgetAllocation, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($budgetAllocationItem, $plantationEntity);

        if ((int) $budgetAllocationItem->finance_budget_allocation_id !== (int) $financeBudgetAllocation->id) {
            abort(404);
        }

        try {
            $this->allocations->deleteItem($budgetAllocationItem);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.budgets.show', [$plantationEntity, $financeBudgetAllocation])
            ->with('success', 'Item alokasi dihapus.');
    }

    public function storeRealization(
        StoreBudgetRealizationRequest $request,
        PlantationEntity $plantationEntity,
        FinanceBudgetAllocation $financeBudgetAllocation,
        BudgetAllocationItem $budgetAllocationItem,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($financeBudgetAllocation, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($budgetAllocationItem, $plantationEntity);

        if ((int) $budgetAllocationItem->finance_budget_allocation_id !== (int) $financeBudgetAllocation->id) {
            abort(404);
        }

        try {
            $this->allocations->addRealization($budgetAllocationItem, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.budgets.show', [$plantationEntity, $financeBudgetAllocation])
            ->with('success', 'Realisasi dicatat.');
    }

    public function destroyRealization(
        PlantationEntity $plantationEntity,
        FinanceBudgetAllocation $financeBudgetAllocation,
        BudgetAllocationItem $budgetAllocationItem,
        BudgetRealization $budgetRealization,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($financeBudgetAllocation, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($budgetAllocationItem, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($budgetRealization, $plantationEntity);

        if ((int) $budgetRealization->budget_allocation_item_id !== (int) $budgetAllocationItem->id) {
            abort(404);
        }

        if ($budgetRealization->source_type !== RealizationSourceType::MANUAL) {
            return back()->with('error', 'Realisasi otomatis dibatalkan dari sumber transaksinya.');
        }

        $this->allocations->reverseRealization($budgetRealization, 'Dibatalkan dari UI anggaran');

        return redirect()
            ->route('plantation.budgets.show', [$plantationEntity, $financeBudgetAllocation])
            ->with('success', 'Realisasi dibatalkan.');
    }
}
