<?php

namespace App\Providers;

use App\Models\PlantationEntity;
use App\Support\EntityRouteBinder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('plantationEntity', function (string $value) {
            return PlantationEntity::query()
                ->where('public_id', $value)
                ->firstOrFail();
        });

        Route::bind('plantation', fn (string $value) => EntityRouteBinder::plantation($value));
        Route::bind('plantationBlock', fn (string $value) => EntityRouteBinder::plantationBlock($value));
        Route::bind('worker', fn (string $value) => EntityRouteBinder::worker($value));
        Route::bind('workType', fn (string $value) => EntityRouteBinder::workType($value));
        Route::bind('supplier', fn (string $value) => EntityRouteBinder::supplier($value));
        Route::bind('inventoryItem', fn (string $value) => EntityRouteBinder::inventoryItem($value));
        Route::bind('financeBudgetAllocation', fn (string $value) => EntityRouteBinder::financeBudgetAllocation($value));
        Route::bind('budgetAllocationItem', fn (string $value) => EntityRouteBinder::budgetAllocationItem($value));
        Route::bind('budgetRealization', fn (string $value) => EntityRouteBinder::budgetRealization($value));
        Route::bind('workActivity', fn (string $value) => EntityRouteBinder::workActivity($value));
        Route::bind('workAttendance', fn (string $value) => EntityRouteBinder::workAttendance($value));
        Route::bind('workerPayroll', fn (string $value) => EntityRouteBinder::workerPayroll($value));
        Route::bind('inventoryPurchase', fn (string $value) => EntityRouteBinder::inventoryPurchase($value));
        Route::bind('materialUsage', fn (string $value) => EntityRouteBinder::materialUsage($value));
        Route::bind('fertilizerApplication', fn (string $value) => EntityRouteBinder::fertilizerApplication($value));
        Route::bind('buyer', fn (string $value) => EntityRouteBinder::buyer($value));
        Route::bind('harvest', fn (string $value) => EntityRouteBinder::harvest($value));
        Route::bind('harvestSale', fn (string $value) => EntityRouteBinder::harvestSale($value));
        Route::bind('harvestSalePayment', fn (string $value) => EntityRouteBinder::harvestSalePayment($value));
    }
}
