<?php

namespace App\Http\Controllers;

use App\Enums\InventoryDocumentStatus;
use App\Enums\PayrollStatus;
use App\Enums\ProductionDocumentStatus;
use App\Models\FinanceBudgetAllocation;
use App\Models\Harvest;
use App\Models\HarvestSale;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\Supplier;
use App\Models\WorkActivity;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\InventoryStockService;
use App\Services\ProductionReportService;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly ProductionReportService $production,
    ) {}

    public function show(PlantationEntity $plantationEntity): View
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $monthStartDate = $monthStart->toDateString();
        $monthEndDate = $monthEnd->toDateString();

        $postedWagesThisMonth = WorkerPayroll::query()
            ->forEntity($plantationEntity)
            ->where('payroll_status', PayrollStatus::POSTED)
            ->whereHas('activity', fn ($query) => $query->whereBetween('activity_date', [$monthStart, $monthEnd]))
            ->sum('final_amount');

        $lowStockCount = InventoryItem::query()
            ->forEntity($plantationEntity)
            ->where('is_active', true)
            ->get()
            ->filter(fn (InventoryItem $item) => $this->stock->isLowStock($item))
            ->count();

        return view('dashboard.index', [
            'entity' => $plantationEntity,
            'plantationCount' => Plantation::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'blockCount' => PlantationBlock::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'workerCount' => Worker::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'supplierCount' => Supplier::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'inventoryCount' => InventoryItem::query()->forEntity($plantationEntity)->where('is_active', true)->count(),
            'lowStockCount' => $lowStockCount,
            'budgetCount' => FinanceBudgetAllocation::query()->forEntity($plantationEntity)->count(),
            'activityCountThisMonth' => WorkActivity::query()
                ->forEntity($plantationEntity)
                ->whereBetween('activity_date', [$monthStart, $monthEnd])
                ->count(),
            'postedWagesThisMonth' => Money::normalize($postedWagesThisMonth),
            'purchaseValueThisMonth' => Money::normalize(
                InventoryPurchase::query()
                    ->forEntity($plantationEntity)
                    ->where('status', InventoryDocumentStatus::POSTED)
                    ->whereBetween('purchase_date', [$monthStart, $monthEnd])
                    ->sum('total_amount')
            ),
            'unpaidWages' => Money::normalize(
                WorkerPayroll::query()
                    ->forEntity($plantationEntity)
                    ->where('payroll_status', PayrollStatus::POSTED)
                    ->where('payment_status', 'UNPAID')
                    ->sum('final_amount')
            ),
            'harvestCountThisMonth' => Harvest::query()
                ->forEntity($plantationEntity)
                ->where('status', ProductionDocumentStatus::POSTED)
                ->whereBetween('harvest_date', [$monthStart, $monthEnd])
                ->count(),
            'productionGroupsThisMonth' => $this->production->summary($plantationEntity, [
                'period_start' => $monthStartDate,
                'period_end' => $monthEndDate,
            ])['groups'],
            'salesThisMonth' => Money::normalize(
                HarvestSale::query()
                    ->forEntity($plantationEntity)
                    ->where('status', ProductionDocumentStatus::POSTED)
                    ->whereBetween('sale_date', [$monthStart, $monthEnd])
                    ->sum('total_amount')
            ),
            'receivedThisMonth' => $this->production->receivedThisMonth($plantationEntity),
            'salesOutstanding' => $this->production->summary($plantationEntity)['outstanding'],
            'unsoldGroups' => $this->production->unsoldGroups($plantationEntity),
        ]);
    }
}
