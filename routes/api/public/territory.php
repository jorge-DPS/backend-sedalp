<?php

use App\Http\Controllers\Api\Public\Territory\TerritorialCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('throttle:public-api')->name('public.')->group(function (): void {
    Route::get('regions', [TerritorialCatalogController::class, 'regions'])->name('regions.index');
    Route::get('provinces', [TerritorialCatalogController::class, 'provinces'])->name('provinces.index');
    Route::get('municipalities', [TerritorialCatalogController::class, 'municipalities'])->name('municipalities.index');
    Route::get('municipalities/{municipality}', [TerritorialCatalogController::class, 'show'])->whereNumber('municipality')->name('municipalities.show');
});
