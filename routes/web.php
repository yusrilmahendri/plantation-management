<?php

use App\Http\Controllers\BuyerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FertilizerApplicationController;
use App\Http\Controllers\FinanceBudgetAllocationController;
use App\Http\Controllers\FinanceIntegrationController;
use App\Http\Controllers\HarvestController;
use App\Http\Controllers\HarvestSaleController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryPurchaseController;
use App\Http\Controllers\MaterialUsageController;
use App\Http\Controllers\PlantationAccessController;
use App\Http\Controllers\PlantationBlockController;
use App\Http\Controllers\PlantationController;
use App\Http\Controllers\ProductionReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\WorkActivityController;
use App\Http\Controllers\WorkTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/access/{token}', [PlantationAccessController::class, 'show'])
    ->name('plantation.access');

Route::prefix('p/{plantationEntity}')
    ->middleware('plantation.access')
    ->name('plantation.')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');

        Route::get('work-activities', [WorkActivityController::class, 'index'])->name('work-activities.index');
        Route::get('work-activities/create', [WorkActivityController::class, 'create'])->name('work-activities.create');
        Route::post('work-activities', [WorkActivityController::class, 'store'])->name('work-activities.store');
        Route::get('work-activities/{workActivity}', [WorkActivityController::class, 'show'])->name('work-activities.show');
        Route::get('work-activities/{workActivity}/edit', [WorkActivityController::class, 'edit'])->name('work-activities.edit');
        Route::put('work-activities/{workActivity}', [WorkActivityController::class, 'update'])->name('work-activities.update');
        Route::delete('work-activities/{workActivity}', [WorkActivityController::class, 'destroy'])->name('work-activities.destroy');
        Route::post('work-activities/{workActivity}/complete', [WorkActivityController::class, 'complete'])->name('work-activities.complete');
        Route::post('work-activities/{workActivity}/cancel', [WorkActivityController::class, 'cancel'])->name('work-activities.cancel');
        Route::post('work-activities/{workActivity}/attendances', [WorkActivityController::class, 'storeAttendance'])->name('work-activities.attendances.store');
        Route::put('work-activities/{workActivity}/attendances/{workAttendance}', [WorkActivityController::class, 'updateAttendance'])->name('work-activities.attendances.update');
        Route::delete('work-activities/{workActivity}/attendances/{workAttendance}', [WorkActivityController::class, 'destroyAttendance'])->name('work-activities.attendances.destroy');
        Route::post('work-activities/{workActivity}/payrolls/generate', [WorkActivityController::class, 'generatePayroll'])->name('work-activities.payrolls.generate');
        Route::put('work-activities/{workActivity}/payrolls/{workerPayroll}', [WorkActivityController::class, 'updatePayroll'])->name('work-activities.payrolls.update');
        Route::post('work-activities/{workActivity}/payrolls/{workerPayroll}/post', [WorkActivityController::class, 'postPayroll'])->name('work-activities.payrolls.post');
        Route::post('work-activities/{workActivity}/payrolls/{workerPayroll}/cancel', [WorkActivityController::class, 'cancelPayroll'])->name('work-activities.payrolls.cancel');
        Route::post('work-activities/{workActivity}/payrolls/{workerPayroll}/pay', [WorkActivityController::class, 'markPayrollPaid'])->name('work-activities.payrolls.pay');

        Route::get('budgets', [FinanceBudgetAllocationController::class, 'index'])->name('budgets.index');
        Route::get('budgets/{financeBudgetAllocation}', [FinanceBudgetAllocationController::class, 'show'])->name('budgets.show');
        Route::post('budgets/{financeBudgetAllocation}/items', [FinanceBudgetAllocationController::class, 'storeItem'])->name('budgets.items.store');
        Route::delete('budgets/{financeBudgetAllocation}/items/{budgetAllocationItem}', [FinanceBudgetAllocationController::class, 'destroyItem'])->name('budgets.items.destroy');
        Route::post('budgets/{financeBudgetAllocation}/items/{budgetAllocationItem}/realizations', [FinanceBudgetAllocationController::class, 'storeRealization'])->name('budgets.realizations.store');
        Route::delete('budgets/{financeBudgetAllocation}/items/{budgetAllocationItem}/realizations/{budgetRealization}', [FinanceBudgetAllocationController::class, 'destroyRealization'])->name('budgets.realizations.destroy');

        Route::resource('plantations', PlantationController::class)->except(['show']);
        Route::resource('blocks', PlantationBlockController::class)
            ->parameters(['blocks' => 'plantationBlock']);
        Route::resource('workers', WorkerController::class);
        Route::resource('work-types', WorkTypeController::class)
            ->parameters(['work-types' => 'workType'])
            ->except(['show']);
        Route::resource('suppliers', SupplierController::class);
        Route::resource('inventory-items', InventoryItemController::class)
            ->parameters(['inventory-items' => 'inventoryItem']);
        Route::get('stock-adjustments/create', [StockAdjustmentController::class, 'create'])->name('stock-adjustments.create');
        Route::post('stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');

        Route::resource('purchases', InventoryPurchaseController::class)
            ->parameters(['purchases' => 'inventoryPurchase']);
        Route::post('purchases/{inventoryPurchase}/post', [InventoryPurchaseController::class, 'post'])->name('purchases.post');
        Route::post('purchases/{inventoryPurchase}/cancel', [InventoryPurchaseController::class, 'cancel'])->name('purchases.cancel');

        Route::resource('material-usages', MaterialUsageController::class)
            ->parameters(['material-usages' => 'materialUsage']);
        Route::post('material-usages/{materialUsage}/post', [MaterialUsageController::class, 'post'])->name('material-usages.post');
        Route::post('material-usages/{materialUsage}/cancel', [MaterialUsageController::class, 'cancel'])->name('material-usages.cancel');

        Route::resource('fertilizer-applications', FertilizerApplicationController::class)
            ->parameters(['fertilizer-applications' => 'fertilizerApplication']);
        Route::post('fertilizer-applications/{fertilizerApplication}/post', [FertilizerApplicationController::class, 'post'])->name('fertilizer-applications.post');
        Route::post('fertilizer-applications/{fertilizerApplication}/cancel', [FertilizerApplicationController::class, 'cancel'])->name('fertilizer-applications.cancel');

        Route::resource('buyers', BuyerController::class);
        Route::resource('harvests', HarvestController::class);
        Route::post('harvests/{harvest}/post', [HarvestController::class, 'post'])->name('harvests.post');
        Route::post('harvests/{harvest}/cancel', [HarvestController::class, 'cancel'])->name('harvests.cancel');

        Route::resource('harvest-sales', HarvestSaleController::class)
            ->parameters(['harvest-sales' => 'harvestSale']);
        Route::post('harvest-sales/{harvestSale}/post', [HarvestSaleController::class, 'post'])->name('harvest-sales.post');
        Route::post('harvest-sales/{harvestSale}/cancel', [HarvestSaleController::class, 'cancel'])->name('harvest-sales.cancel');
        Route::post('harvest-sales/{harvestSale}/payments', [HarvestSaleController::class, 'storePayment'])->name('harvest-sales.payments.store');
        Route::post('harvest-sales/{harvestSale}/payments/{harvestSalePayment}/reverse', [HarvestSaleController::class, 'reversePayment'])->name('harvest-sales.payments.reverse');

        Route::get('production-reports', [ProductionReportController::class, 'show'])->name('production-reports.show');

        Route::get('integration', [FinanceIntegrationController::class, 'show'])->name('integration.show');
        Route::post('integration/{outbox}/retry', [FinanceIntegrationController::class, 'retry'])->name('integration.retry');
    });
