<?php

use App\Models\Investment\ProjectBudget;
use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('SELECT current_database() AS database')->database)->toBe('sedalp_testing');
});

it('reconstruye repetidamente la base de pruebas conservando las funciones y restaurando sus triggers', function (): void {
    $functionNames = [
        'simred_guard_poa_document_history',
        'simred_guard_project_source',
        'simred_guard_source_owner',
        'simred_guard_budget_update',
        'simred_guard_budget_revision',
        'simred_check_revision_applied',
    ];
    $triggerNames = [
        'municipal_poa_documents_history_guard',
        'projects_source_institution_guard',
        'investment_sources_owner_guard',
        'project_budgets_update_guard',
        'project_budget_revisions_history_guard',
        'project_budget_revision_applied_check',
    ];
    $functionOids = fn (): array => DB::table('pg_proc as functions')
        ->join('pg_namespace as namespaces', 'namespaces.oid', '=', 'functions.pronamespace')
        ->where('namespaces.nspname', DB::raw('current_schema()'))
        ->whereIn('functions.proname', $functionNames)
        ->where('functions.pronargs', 0)
        ->pluck('functions.oid', 'functions.proname')->sortKeys()->all();
    $originalFunctions = $functionOids();

    expect($originalFunctions)->toHaveCount(6);

    for ($rebuild = 0; $rebuild < 2; $rebuild++) {
        $this->artisan('migrate:fresh', [
            '--database' => DB::connection()->getName(),
            '--force' => true,
            '--no-interaction' => true,
        ])->assertSuccessful();

        expect($functionOids())->toBe($originalFunctions)
            ->and(DB::table('pg_trigger')->whereIn('tgname', $triggerNames)->where('tgenabled', 'O')->count())->toBe(6);

        $document = MunicipalPoaDocument::factory()->create();
        $originalPath = $document->pdf_path;

        expect(fn () => DB::transaction(fn () => $document->delete()))->toThrow(QueryException::class);
        expect(fn () => DB::transaction(fn () => $document->update(['pdf_path' => 'municipal/poas/replaced.pdf'])))
            ->toThrow(QueryException::class);
        expect($document->fresh()->pdf_path)->toBe($originalPath);

        $budget = ProjectBudget::factory()->reported('100.00')->create();

        expect(fn () => DB::transaction(fn () => $budget->update(['amount_bob' => '200.00'])))
            ->toThrow(QueryException::class);
        expect($budget->fresh()->amount_bob)->toBe('100.00');
    }
});

it('elimina las funciones y triggers propios al revertir y los restaura al migrar nuevamente', function (string $migrationPath, array $functionNames, array $triggerNames): void {
    $migration = require database_path($migrationPath);
    $historyMigration = require database_path('migrations/2026_11_05_202629_add_source_history_to_project_budget_revisions_table.php');

    if (in_array('simred_guard_budget_update', $functionNames, true)) {
        $historyMigration->down();
    }

    $migration->down();

    foreach ($functionNames as $name) {
        expect(DB::selectOne('SELECT to_regprocedure(?) IS NULL AS missing', [$name.'()'])->missing)->toBeTrue();
    }

    expect(DB::table('pg_trigger')->whereIn('tgname', $triggerNames)->exists())->toBeFalse();

    $migration->up();

    if (in_array('simred_guard_budget_update', $functionNames, true)) {
        $historyMigration->up();
    }

    foreach ($functionNames as $name) {
        expect(DB::selectOne('SELECT to_regprocedure(?) IS NOT NULL AS present', [$name.'()'])->present)->toBeTrue();
    }

    expect(DB::table('pg_trigger')->whereIn('tgname', $triggerNames)->where('tgenabled', 'O')->count())->toBe(count($triggerNames));
})->with([
    'documentos POA' => [
        'migrations/2026_11_04_180313_create_municipal_poa_documents_table.php',
        ['simred_guard_poa_document_history'],
        ['municipal_poa_documents_history_guard'],
    ],
    'fuentes y revisiones presupuestarias' => [
        'migrations/2026_11_04_180318_add_primary_investment_source_id_to_projects_table.php',
        ['simred_guard_project_source', 'simred_guard_source_owner', 'simred_guard_budget_update', 'simred_guard_budget_revision', 'simred_check_revision_applied'],
        ['projects_source_institution_guard', 'investment_sources_owner_guard', 'project_budgets_update_guard', 'project_budget_revisions_history_guard', 'project_budget_revision_applied_check'],
    ],
]);
