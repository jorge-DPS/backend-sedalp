<?php

use App\Http\Controllers\Api\Public\Municipal\MunicipalAuthorityController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalPoaController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalPoaDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/municipalities/{municipality}')->whereNumber('municipality')->middleware('throttle:public-api')
    ->scopeBindings()->name('public.municipal.')->group(function (): void {
        Route::get('poas', [MunicipalPoaController::class, 'index'])->name('poas.index');
        Route::get('poas/{municipalPoa}', [MunicipalPoaController::class, 'show'])->whereNumber('municipalPoa')->name('poas.show');
        Route::prefix('poas/{municipalPoa}/documents')->whereNumber('municipalPoa')->name('documents.')->group(function (): void {
            Route::get('/', [MunicipalPoaDocumentController::class, 'index'])->name('index');
            Route::get('{document}', [MunicipalPoaDocumentController::class, 'show'])->whereNumber('document')->name('show');
            Route::get('{document}/file', [MunicipalPoaDocumentController::class, 'file'])->whereNumber('document')->name('file');
        });
        Route::get('authorities', [MunicipalAuthorityController::class, 'index'])->name('authorities.index');
        Route::get('authorities/{municipalAuthority}', [MunicipalAuthorityController::class, 'show'])->whereNumber('municipalAuthority')->name('authorities.show');
        Route::get('authorities/{municipalAuthority}/photo', [MunicipalAuthorityController::class, 'photo'])->whereNumber('municipalAuthority')->name('authorities.photo');
    });
