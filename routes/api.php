<?php

use App\Http\Controllers\Internal\AccessLinkController;
use App\Http\Controllers\Internal\BudgetAllocationController;
use App\Http\Controllers\Internal\HarvestSaleController;
use App\Http\Controllers\Internal\PlantationEntityController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')
    ->middleware('internal.finance')
    ->group(function () {
        Route::post('plantation-entities', [PlantationEntityController::class, 'store'])
            ->name('internal.plantation-entities.store');
        Route::put('plantation-entities/{plantationEntity}', [PlantationEntityController::class, 'update'])
            ->name('internal.plantation-entities.update');
        Route::post('plantation-entities/{plantationEntity}/activate', [PlantationEntityController::class, 'activate'])
            ->name('internal.plantation-entities.activate');
        Route::post('plantation-entities/{plantationEntity}/deactivate', [PlantationEntityController::class, 'deactivate'])
            ->name('internal.plantation-entities.deactivate');

        Route::get('plantation-entities/{plantationEntity}/access-links', [AccessLinkController::class, 'index'])
            ->name('internal.access-links.index');
        Route::post('plantation-entities/{plantationEntity}/access-links', [AccessLinkController::class, 'store'])
            ->name('internal.access-links.store');
        Route::post('plantation-entities/{plantationEntity}/access-links/{tokenId}/revoke', [AccessLinkController::class, 'revoke'])
            ->name('internal.access-links.revoke');
        Route::post('plantation-entities/{plantationEntity}/access-links/{tokenId}/activate', [AccessLinkController::class, 'activate'])
            ->name('internal.access-links.activate');
        Route::post('plantation-entities/{plantationEntity}/access-links/{tokenId}/regenerate', [AccessLinkController::class, 'regenerate'])
            ->name('internal.access-links.regenerate');
        Route::delete('plantation-entities/{plantationEntity}/access-links/{tokenId}', [AccessLinkController::class, 'destroy'])
            ->name('internal.access-links.destroy');

        Route::put('budget-allocations/{budgetPublicId}', [BudgetAllocationController::class, 'upsert'])
            ->name('internal.budget-allocations.upsert');

        Route::get('plantation-entities/{plantationEntity}/harvest-sales', [HarvestSaleController::class, 'index'])
            ->name('internal.harvest-sales.index');
    });
