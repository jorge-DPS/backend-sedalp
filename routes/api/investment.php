<?php

use App\Enums\Auth\PermissionName;
use App\Http\Controllers\Api\Admin\Investment\InvestmentCatalogController;
use App\Http\Controllers\Api\Admin\Investment\InvestmentSourceController;
use App\Http\Controllers\Api\Admin\Investment\ProjectBudgetController;
use App\Http\Controllers\Api\Admin\Investment\ProjectBudgetRevisionController;
use App\Http\Controllers\Api\Admin\Investment\ProjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('investment')->name('investment.')->group(function (): void {
    Route::get('catalogs', InvestmentCatalogController::class)
        ->middleware('can:'.PermissionName::INVESTMENT_CATALOGS_VIEW->value)->name('catalogs');

    Route::apiResource('projects', ProjectController::class)
        ->whereNumber('project')
        ->middlewareFor(['index', 'show'], 'can:'.PermissionName::INVESTMENT_PROJECTS_VIEW->value)
        ->middlewareFor('store', 'can:'.PermissionName::INVESTMENT_PROJECTS_CREATE->value)
        ->middlewareFor('update', 'can:'.PermissionName::INVESTMENT_PROJECTS_UPDATE->value)
        ->middlewareFor('destroy', 'can:'.PermissionName::INVESTMENT_PROJECTS_DELETE->value);

    Route::apiResource('sources', InvestmentSourceController::class)->except('destroy')
        ->whereNumber('source')
        ->middlewareFor(['index', 'show'], 'can:'.PermissionName::INVESTMENT_SOURCES_VIEW->value)
        ->middlewareFor('store', 'can:'.PermissionName::INVESTMENT_SOURCES_CREATE->value)
        ->middlewareFor('update', 'can:'.PermissionName::INVESTMENT_SOURCES_UPDATE->value);

    Route::prefix('projects/{project}/budgets')->whereNumber('project')->group(function (): void {
        Route::get('/', [ProjectBudgetController::class, 'index'])
            ->middleware('can:'.PermissionName::INVESTMENT_BUDGETS_VIEW->value)->name('budgets.index');
        Route::post('/', [ProjectBudgetController::class, 'store'])
            ->middleware('can:'.PermissionName::INVESTMENT_BUDGETS_CREATE->value)->name('budgets.store');
        Route::get('{budget}', [ProjectBudgetController::class, 'show'])->whereNumber('budget')
            ->middleware('can:'.PermissionName::INVESTMENT_BUDGETS_VIEW->value)->name('budgets.show');
        Route::get('{budget}/revisions', [ProjectBudgetRevisionController::class, 'index'])->whereNumber('budget')
            ->middleware('can:'.PermissionName::INVESTMENT_BUDGETS_VIEW->value)->name('budget-revisions.index');
        Route::post('{budget}/revisions', [ProjectBudgetRevisionController::class, 'store'])->whereNumber('budget')
            ->middleware('can:'.PermissionName::INVESTMENT_BUDGETS_REVISE->value)->name('budget-revisions.store');
    });
});
