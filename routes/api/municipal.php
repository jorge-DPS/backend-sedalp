<?php

use App\Enums\Auth\PermissionName;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalAuthorityController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalAuthorityPhotoController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaDocumentController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaUploadController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalPoaVersionController;
use App\Http\Controllers\Api\Admin\Municipal\MunicipalTermController;
use Illuminate\Support\Facades\Route;

Route::prefix('municipalities/{municipality}')->whereNumber('municipality')->name('municipal.')->group(function (): void {
    Route::prefix('poas/{municipalPoa}/versions')->whereNumber('municipalPoa')->name('versions.')->group(function (): void {
        Route::get('/', [MunicipalPoaVersionController::class, 'index'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('index');
        Route::post('/', [MunicipalPoaVersionController::class, 'store'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value)->name('store');
        Route::prefix('{version}')->whereNumber('version')->group(function (): void {
            Route::get('/', [MunicipalPoaVersionController::class, 'show'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('show');
            Route::put('publication', [MunicipalPoaVersionController::class, 'publish'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_PUBLISH->value)->name('publication');
            Route::get('documents', [MunicipalPoaDocumentController::class, 'versionIndex'])->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('documents.index');
            Route::post('documents', [MunicipalPoaDocumentController::class, 'versionStore'])->middleware(['can:'.PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value, 'throttle:30,1'])->name('documents.store');
            Route::get('documents/{document}', [MunicipalPoaDocumentController::class, 'versionShow'])->whereNumber('document')->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('documents.show');
            Route::get('documents/{document}/file', [MunicipalPoaDocumentController::class, 'versionFile'])->whereNumber('document')->middleware('can:'.PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value)->name('documents.file');
            Route::prefix('uploads')->middleware(['can:'.PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value, 'throttle:600,1'])->name('uploads.')->group(function (): void {
                Route::post('/', [MunicipalPoaUploadController::class, 'store'])->name('store');
                Route::get('{upload}', [MunicipalPoaUploadController::class, 'show'])->whereUuid('upload')->name('show');
                Route::put('{upload}/chunks/{index}', [MunicipalPoaUploadController::class, 'chunk'])->whereUuid('upload')->whereNumber('index')->name('chunk');
                Route::post('{upload}/complete', [MunicipalPoaUploadController::class, 'complete'])->whereUuid('upload')->name('complete');
                Route::delete('{upload}', [MunicipalPoaUploadController::class, 'destroy'])->whereUuid('upload')->name('destroy');
            });
        });
    });

    Route::apiResource('terms', MunicipalTermController::class)->parameters(['terms' => 'municipalTerm'])
        ->only(['index', 'store', 'show', 'update'])->whereNumber('municipalTerm')
        ->middlewareFor(['index', 'show'], 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value)
        ->middlewareFor('store', 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value)
        ->middlewareFor('update', 'can:'.PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value);
    Route::prefix('terms/{municipalTerm}/authorities')->whereNumber('municipalTerm')->name('terms.authorities.')->group(function (): void {
        Route::get('/', [MunicipalAuthorityController::class, 'index'])->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value)->name('index');
        Route::post('/', [MunicipalAuthorityController::class, 'store'])->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value)->name('store');
        Route::get('{municipalAuthority}', [MunicipalTermController::class, 'showAuthority'])->whereNumber('municipalAuthority')->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value)->name('show');
        Route::patch('{municipalAuthority}', [MunicipalTermController::class, 'updateAuthority'])->whereNumber('municipalAuthority')->middleware('can:'.PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value)->name('update');
    });

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
