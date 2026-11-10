<?php

use App\Http\Controllers\Api\Public\Investment\DashboardController;
use App\Http\Controllers\Api\Public\Investment\InstitutionController;
use App\Http\Controllers\Api\Public\Investment\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/investment')->middleware('throttle:public-api')->name('public.investment.')->group(function (): void {
    Route::get('institutions', InstitutionController::class)->name('institutions');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
    Route::get('dashboard', [DashboardController::class, 'summary'])->name('dashboard');
    Route::get('distributions', [DashboardController::class, 'distributions'])->name('distributions');
    Route::get('territories/{territoryType}', [DashboardController::class, 'territories'])
        ->whereIn('territoryType', ['regions', 'provinces', 'municipalities'])->name('territories');
});
