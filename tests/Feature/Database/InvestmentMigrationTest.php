<?php

use App\Models\Territory\Province;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('revierte y vuelve a ejecutar las migraciones de inversión sin modificar las provincias existentes', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $province = Province::factory()->create();
    $tables = ['institutions', 'sectors', 'strategic_axes', 'intervention_types', 'project_statuses', 'projects', 'project_provinces', 'project_municipalities', 'investment_sources', 'project_budgets', 'project_budget_revisions'];
    $sourceMigration = require database_path('migrations/2026_11_04_180318_add_primary_investment_source_id_to_projects_table.php');
    $historyMigration = require database_path('migrations/2026_11_05_202629_add_source_history_to_project_budget_revisions_table.php');
    $schemas = [];
    $migrations = [];

    foreach ($tables as $table) {
        $schemas[$table] = [
            'columns' => Schema::getColumns($table),
            'indexes' => Schema::getIndexes($table),
            'foreign_keys' => Schema::getForeignKeys($table),
        ];
        $paths = glob(database_path('migrations/*_create_'.$table.'_table.php'));

        expect($paths)->toHaveCount(1);
        $migrations[] = require $paths[0];
    }

    $historyMigration->down();
    $sourceMigration->down();

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $this->assertModelExists($province);

    foreach ($migrations as $migration) {
        $migration->up();
    }

    $sourceMigration->up();
    $historyMigration->up();

    foreach ($tables as $table) {
        expect([
            'columns' => Schema::getColumns($table),
            'indexes' => Schema::getIndexes($table),
            'foreign_keys' => Schema::getForeignKeys($table),
        ])->toBe($schemas[$table]);
    }

    $this->assertModelExists($province);
});
