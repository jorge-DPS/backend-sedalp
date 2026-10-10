<?php

use App\Http\Controllers\Api\Admin\Territory\MunicipalityController;
use App\Http\Controllers\Api\Admin\Territory\ProvinceController;
use App\Http\Controllers\Api\Admin\Territory\RegionBoundaryController;
use App\Http\Controllers\Api\Admin\Territory\RegionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('regions', RegionController::class)
    ->whereNumber('region')
    ->middlewareFor(['index', 'show'], 'can:regions.view')
    ->middlewareFor('store', 'can:regions.create')
    ->middlewareFor('update', 'can:regions.update')
    ->middlewareFor('destroy', 'can:regions.delete');

Route::apiResource('provinces', ProvinceController::class)
    ->whereNumber('province')
    ->middlewareFor(['index', 'show'], 'can:provinces.view')
    ->middlewareFor('store', 'can:provinces.create')
    ->middlewareFor('update', 'can:provinces.update')
    ->middlewareFor('destroy', 'can:provinces.delete');

Route::apiResource('municipalities', MunicipalityController::class)
    ->whereNumber('municipality')
    ->middlewareFor(['index', 'show'], 'can:municipalities.view')
    ->middlewareFor('store', 'can:municipalities.create')
    ->middlewareFor('update', 'can:municipalities.update')
    ->middlewareFor('destroy', 'can:municipalities.delete');

Route::get('regions/{region}/boundary', RegionBoundaryController::class)
    ->whereNumber('region')
    ->middleware('can:regions.view')
    ->name('regions.boundary.show');
