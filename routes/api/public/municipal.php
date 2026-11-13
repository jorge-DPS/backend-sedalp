<?php

use App\Http\Controllers\Api\Public\Municipal\MunicipalAuthorityController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalPoaController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalPoaDocumentController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalPoaVersionController;
use App\Http\Controllers\Api\Public\Municipal\MunicipalTermController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/municipalities/{municipality}')->whereNumber('municipality')->middleware('throttle:public-api')
    ->scopeBindings()->name('public.municipal.')->group(function (): void {
        Route::get('terms', [MunicipalTermController::class, 'index'])->name('terms.index');
        Route::get('terms/{municipalTerm}', [MunicipalTermController::class, 'show'])->whereNumber('municipalTerm')->name('terms.show');
        Route::get('terms/{municipalTerm}/authorities', [MunicipalTermController::class, 'authorities'])->whereNumber('municipalTerm')->name('terms.authorities.index');
        Route::get('terms/{municipalTerm}/authorities/{municipalAuthority}', [MunicipalTermController::class, 'showAuthority'])->whereNumber('municipalTerm')->whereNumber('municipalAuthority')->name('terms.authorities.show');
        Route::prefix('poas/{municipalPoa}/versions')->whereNumber('municipalPoa')->name('versions.')->group(function (): void {
            Route::get('/', [MunicipalPoaVersionController::class, 'index'])->name('index');
            Route::get('{version}', [MunicipalPoaVersionController::class, 'show'])->whereNumber('version')->name('show');
            Route::get('{version}/documents', [MunicipalPoaDocumentController::class, 'versionIndex'])->whereNumber('version')->name('documents.index');
            Route::get('{version}/documents/{document}', [MunicipalPoaDocumentController::class, 'versionShow'])->whereNumber('version')->whereNumber('document')->name('documents.show');
            Route::get('{version}/documents/{document}/file', [MunicipalPoaDocumentController::class, 'versionFile'])->whereNumber('version')->whereNumber('document')->name('documents.file');
        });
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
