<?php

use App\Http\Controllers\Api\Admin\Territory\MapLayerController;
use App\Http\Controllers\Api\Admin\Territory\MapPublicationController;
use App\Http\Controllers\Api\Admin\Territory\MunicipalityController;
use App\Http\Controllers\Api\Admin\Territory\ProvinceController;
use App\Http\Controllers\Api\Admin\Territory\RegionBoundaryController;
use App\Http\Controllers\Api\Admin\Territory\RegionBoundaryIndexController;
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

Route::controller(MapLayerController::class)->prefix('map-layers')->name('map-layers.')->group(function (): void {
    Route::get('/', 'index')->middleware('can:maps.view')->name('index');
    Route::post('preview', 'preview')->middleware('throttle:20,1')->name('preview');
    Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
    Route::get('{mapLayer}', 'show')->whereNumber('mapLayer')->middleware('can:maps.view')->name('show');
    Route::match(['PUT', 'DELETE'], '{mapLayer}/publication', MapPublicationController::class)->whereNumber('mapLayer')->middleware('throttle:20,1')->name('publication');
    Route::get('{mapLayer}/geojson', 'geoJson')->whereNumber('mapLayer')->middleware('can:maps.view')->name('geojson');
    Route::get('{mapLayer}/versions', 'versions')->whereNumber('mapLayer')->middleware('can:maps.view')->name('versions.index');
    Route::post('{mapLayer}/versions', 'update')->whereNumber('mapLayer')->middleware('throttle:10,1')->name('versions.store');
});

Route::get('region-boundaries', RegionBoundaryIndexController::class)
    ->middleware('can:regions.view')
    ->name('region-boundaries.index');
