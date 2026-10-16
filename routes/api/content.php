<?php

use App\Http\Controllers\Api\Admin\Content\SiteMediaController;
use App\Http\Controllers\Api\Admin\Content\SitePageController;
use App\Http\Controllers\Api\Admin\Content\SiteSectionController;
use App\Http\Controllers\Api\Admin\Content\SiteSectionItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->name('admin.content.')->middleware('throttle:120,1')->group(function (): void {
    Route::get('pages', [SitePageController::class, 'index'])->middleware('can:content.view')->name('pages.index');
    Route::get('pages/{page:key}', [SitePageController::class, 'show'])->middleware('can:content.view')->name('pages.show');
    Route::patch('pages/{page:key}/sections/{section:key}', [SiteSectionController::class, 'update'])->middleware('can:content.update')->name('sections.update');
    Route::post('pages/{page:key}/sections/{section:key}/items', [SiteSectionItemController::class, 'store'])->middleware('can:content.update')->name('items.store');
    Route::patch('pages/{page:key}/sections/{section:key}/items/{item}', [SiteSectionItemController::class, 'update'])->middleware('can:content.update')->name('items.update');
    Route::delete('pages/{page:key}/sections/{section:key}/items/{item}', [SiteSectionItemController::class, 'destroy'])->middleware('can:content.update')->name('items.destroy');
    Route::get('media', [SiteMediaController::class, 'index'])->middleware('can:content.view')->name('media.index');
    Route::post('media', [SiteMediaController::class, 'store'])->middleware(['can:content.media.create', 'throttle:20,1'])->name('media.store');
    Route::delete('media/{media}', [SiteMediaController::class, 'destroy'])->middleware('can:content.media.delete')->name('media.destroy');
});
