<?php

use App\Enums\Auth\PermissionName;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalAuthorityController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalAuthorityPhotoController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('municipalities/{municipality}')->whereNumber('municipality')->name('municipal.')->group(function (): void {
    Route::apiResource('poas', MunicipalPoaController::class)->parameters(['poas' => 'municipalPoa'])
        ->only(['index', 'store', 'show', 'update'])->whereNumber('municipalPoa')
        ->middlewareFor(['index', 'show'], 'can:'.PermissionName::MUNICIPAL_POAS_VIEW->value)
        ->middlewareFor('store', 'can:'.PermissionName::MUNICIPAL_POAS_CREATE->value)
        ->middlewareFor('update', 'can:'.PermissionName::MUNICIPAL_POAS_UPDATE->value);

    Route::prefix('poas/{municipalPoa}/documents')->whereNumber('municipalPoa')->name('documents.')->group(function (): void {
        Route::get('/', [MunicipalPoaDocumentController::class, 'index'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('index');
        Route::post('/', [MunicipalPoaDocumentController::class, 'store'])->middleware(['can:'.PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value, 'throttle:10,1'])->name('store');
        Route::get('{document}', [MunicipalPoaDocumentController::class, 'show'])->whereNumber('document')->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('show');
        Route::get('{document}/file', [MunicipalPoaDocumentController::class, 'file'])->whereNumber('document')->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('file');
        Route::put('{document}/publication', [MunicipalPoaDocumentController::class, 'publish'])->whereNumber('document')->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_PUBLISH->value)->name('publication');
    });

    Route::apiResource('authorities', MunicipalAuthorityController::class)->parameters(['authorities' => 'municipalAuthority'])
        ->only(['index', 'store', 'show', 'update'])->whereNumber('municipalAuthority')
        ->middlewareFor(['index', 'show'], 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value)
        ->middlewareFor('store', 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value)
        ->middlewareFor('update', 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value);
    Route::put('authorities/{municipalAuthority}/publication', [MunicipalAuthorityController::class, 'publish'])->whereNumber('municipalAuthority')
        ->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_PUBLISH->value)->name('authorities.publication');
    Route::put('authorities/{municipalAuthority}/current', [MunicipalAuthorityController::class, 'activate'])->whereNumber('municipalAuthority')
        ->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value)->name('authorities.current');
    Route::get('authorities/{municipalAuthority}/photo', [MunicipalAuthorityPhotoController::class, 'show'])->whereNumber('municipalAuthority')
        ->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value)->name('authorities.photo.show');
    Route::post('authorities/{municipalAuthority}/photo', [MunicipalAuthorityPhotoController::class, 'store'])->whereNumber('municipalAuthority')
        ->middleware(['can:'.PermissionName::MUNICIPAL_AUTHORITY_PHOTOS_UPDATE->value, 'throttle:20,1'])->name('authorities.photo.store');
});
