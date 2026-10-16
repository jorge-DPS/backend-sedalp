<?php

use App\Http\Controllers\Api\Public\MapLayerController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->middleware('throttle:public-api')->controller(MapLayerController::class)->group(function (): void {
    Route::get('map-layers', 'index')->name('public.map-layers.index');
    Route::get('map-layers/{mapLayer}', 'show')->whereNumber('mapLayer')->name('public.map-layers.show');
    Route::get('map-layers/{mapLayer}/geojson', 'geoJson')->whereNumber('mapLayer')->name('public.map-layers.geojson');
    Route::get('region-boundaries', 'boundaries')->name('public.region-boundaries.index');
});
