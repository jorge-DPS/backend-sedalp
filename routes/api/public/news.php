<?php

use App\Http\Controllers\Api\Public\Communication\NewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/news')
    ->middleware('throttle:public-api')
    ->controller(NewsController::class)
    ->group(function (): void {
        Route::get('/', 'index')
            ->name('public.news.index');

        Route::get('/{slug}', 'show')
            ->where('slug', '[A-Za-z0-9-]+')
            ->name('public.news.show');
    });
