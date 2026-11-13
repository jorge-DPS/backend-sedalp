<?php

use App\Models\Investment\ProjectBudget;
use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('SELECT current_database() AS database')->database)->toBe('sedalp_testing');
});

it('reconstruye fixtures en un esquema aislado conservando funciones y restaurando sus triggers', function (): void {
    $originalCounts = [];
    foreach (['regions', 'provinces', 'municipalities', 'municipal_poas', 'municipal_poa_documents', 'municipal_poa_versions', 'projects', 'project_budgets', 'institutions'] as $table) {
        $originalCounts[$table] = DB::table($table)->count();
    }
    $schema = 'simred_lifecycle_test_'.Str::lower(Str::random(16));
    $originalConnection = DB::getDefaultConnection();
    config(['database.connections.migration_lifecycle' => [
        ...config('database.connections.pgsql'), 'search_path' => $schema,
    ]]);
    $connection = DB::connection('migration_lifecycle');
    $connection->statement('CREATE SCHEMA "'.$schema.'"');
    try {
        DB::setDefaultConnection('migration_lifecycle');
        Schema::clearResolvedInstance('db.schema');
        $connection->beginTransaction();
        $paths = [];
        foreach ([
            'positions', 'professions', 'organizational_units', 'staff_members', 'users',
            'regions', 'provinces', 'municipalities', 'institutions', 'sectors', 'strategic_axes',
            'intervention_types', 'project_statuses', 'projects', 'municipal_poas', 'municipal_poa_documents',
            'municipal_authorities', 'investment_sources', 'project_budgets', 'project_budget_revisions',
        ] as $table) {
            $matches = glob(database_path('migrations/*_create_'.$table.'_table.php'));
            expect($matches)->toHaveCount(1);
            $paths[] = $matches[0];
        }
        foreach ([
            '2026_11_04_180318_add_primary_investment_source_id_to_projects_table.php',
            '2026_11_05_202629_add_source_history_to_project_budget_revisions_table.php',
            '2026_11_13_160924_normalize_municipal_poa_versions.php',
            '2026_11_13_160925_create_municipal_terms_table.php',
            '2026_11_13_160926_create_municipal_poa_uploads_table.php',
        ] as $name) {
            $paths[] = database_path('migrations/'.$name);
        }
        $migrate = static function () use ($paths): void {
            foreach ($paths as $path) {
                (require $path)->up();
            }
        };
        $migrate();
        $functionNames = [
            'simred_guard_poa_document_history', 'simred_guard_poa_version_history',
            'simred_guard_project_source', 'simred_guard_source_owner', 'simred_guard_budget_update',
            'simred_guard_budget_revision', 'simred_check_revision_applied',
        ];
        $triggerNames = [
            'municipal_poa_documents_history_guard', 'municipal_poa_versions_history_guard',
            'projects_source_institution_guard', 'investment_sources_owner_guard',
            'project_budgets_update_guard', 'project_budget_revisions_history_guard', 'project_budget_revision_applied_check',
        ];
        $functionOids = fn (): array => DB::table('pg_proc as functions')
            ->join('pg_namespace as namespaces', 'namespaces.oid', '=', 'functions.pronamespace')
            ->where('namespaces.nspname', $schema)->whereIn('functions.proname', $functionNames)
            ->where('functions.pronargs', 0)->pluck('functions.oid', 'functions.proname')->sortKeys()->all();
        $originalFunctions = $functionOids();
        expect($originalFunctions)->toHaveCount(7);
        for ($rebuild = 0; $rebuild < 2; $rebuild++) {
            $tables = $connection->table('pg_catalog.pg_tables')->where('schemaname', $schema)->pluck('tablename');
            $qualifiedNames = $tables->map(fn (string $table): string => '"'.$schema.'"."'.str_replace('"', '""', $table).'"');
            $connection->statement('DROP TABLE '.$qualifiedNames->implode(', ').' CASCADE');
            expect($functionOids())->toBe($originalFunctions);
            $migrate();
            expect($functionOids())->toBe($originalFunctions)
                ->and(DB::table('pg_trigger as triggers')->join('pg_class as tables', 'tables.oid', '=', 'triggers.tgrelid')
                    ->join('pg_namespace as namespaces', 'namespaces.oid', '=', 'tables.relnamespace')
                    ->where('namespaces.nspname', $schema)->whereIn('triggers.tgname', $triggerNames)
                    ->where('triggers.tgenabled', 'O')->count())->toBe(7);
            $document = MunicipalPoaDocument::factory()->create();
            $originalPath = $document->pdf_path;
            expect(fn () => DB::transaction(fn () => $document->delete()))->toThrow(QueryException::class);
            expect(fn () => DB::transaction(fn () => $document->update(['pdf_path' => 'municipal/poas/replaced.pdf'])))->toThrow(QueryException::class);
            expect($document->fresh()->pdf_path)->toBe($originalPath);
            $budget = ProjectBudget::factory()->reported('100.00')->create();
            expect(fn () => DB::transaction(fn () => $budget->update(['amount_bob' => '200.00'])))->toThrow(QueryException::class);
            expect($budget->fresh()->amount_bob)->toBe('100.00');
        }
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        if (preg_match('/^simred_lifecycle_test_[a-z0-9]{16}$/D', $schema) === 1) {
            $connection->statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
        DB::purge('migration_lifecycle');
    }
    foreach ($originalCounts as $table => $count) {
        expect(DB::table($table)->count())->toBe($count);
    }
});

it('elimina las funciones y triggers propios al revertir y los restaura al migrar nuevamente', function (string $migrationPath, array $functionNames, array $triggerNames): void {
    $migration = require database_path($migrationPath);
    $historyMigration = require database_path('migrations/2026_11_05_202629_add_source_history_to_project_budget_revisions_table.php');
    $normalization = require database_path('migrations/2026_11_13_160924_normalize_municipal_poa_versions.php');
    $uploads = require database_path('migrations/2026_11_13_160926_create_municipal_poa_uploads_table.php');
    $isPoa = in_array('simred_guard_poa_document_history', $functionNames, true);
    if ($isPoa) {
        $uploads->down();
        $normalization->down();
    }

    if (in_array('simred_guard_budget_update', $functionNames, true)) {
        $historyMigration->down();
    }

    $migration->down();

    foreach ($functionNames as $name) {
        expect(DB::selectOne('SELECT to_regprocedure(?) IS NULL AS missing', [$name.'()'])->missing)->toBeTrue();
    }

    expect(DB::table('pg_trigger')->whereIn('tgname', $triggerNames)->exists())->toBeFalse();

    $migration->up();
    if ($isPoa) {
        $normalization->up();
        $uploads->up();
    }

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
        ['simred_guard_poa_document_history', 'simred_guard_poa_version_history'],
        ['municipal_poa_documents_history_guard', 'municipal_poa_versions_history_guard'],
    ],
    'fuentes y revisiones presupuestarias' => [
        'migrations/2026_11_04_180318_add_primary_investment_source_id_to_projects_table.php',
        ['simred_guard_project_source', 'simred_guard_source_owner', 'simred_guard_budget_update', 'simred_guard_budget_revision', 'simred_check_revision_applied'],
        ['projects_source_institution_guard', 'investment_sources_owner_guard', 'project_budgets_update_guard', 'project_budget_revisions_history_guard', 'project_budget_revision_applied_check'],
    ],
]);
