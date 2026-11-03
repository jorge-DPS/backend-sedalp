<?php

use App\Models\Investment\Project;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

dataset('project territories', [
    'provincias' => ['provinces', 'project_provinces', 'province_id', Province::class],
    'municipios' => ['municipalities', 'project_municipalities', 'municipality_id', Municipality::class],
]);

it('asocia múltiples territorios y proyectos en ambos sentidos con timestamps', function (string $relation, string $table, string $foreignKey, string $model): void {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $territories = $model::factory()->count(2)->create();

    $project->{$relation}()->attach($territories->modelKeys());
    $territories[0]->projects()->attach($otherProject->id);

    expect($project->{$relation}()->orderBy('id')->get()->modelKeys())->toBe($territories->modelKeys())
        ->and($territories[0]->projects()->orderBy('id')->get()->modelKeys())->toBe([$project->id, $otherProject->id])
        ->and($territories[1]->projects()->sole()->is($project))->toBeTrue()
        ->and($otherProject->{$relation}()->sole()->is($territories[0]))->toBeTrue();

    foreach ($project->{$relation} as $territory) {
        expect($territory->pivot->created_at)->not->toBeNull()
            ->and($territory->pivot->updated_at)->not->toBeNull();
    }

    $inverse = $territories[0]->projects()->whereKey($otherProject->id)->sole();
    expect($inverse->pivot->created_at)->not->toBeNull()
        ->and($inverse->pivot->updated_at)->not->toBeNull()
        ->and(DB::table($table)->count())->toBe(3);
})->with('project territories');

it('impide repetir la asociación entre un proyecto y un territorio', function (string $relation, string $table, string $foreignKey, string $model): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $project->{$relation}()->attach($territory->id);

    expect(fn () => DB::transaction(fn () => $project->{$relation}()->attach($territory->id)))
        ->toThrow(QueryException::class);

    expect(DB::table($table)->where('project_id', $project->id)->where($foreignKey, $territory->id)->count())->toBe(1);
})->with('project territories');

it('impide referencias inexistentes en ambos lados de las tablas intermedias', function (string $relation, string $table, string $foreignKey, string $model, bool $invalidProject): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $attributes = [
        'project_id' => $invalidProject ? 999999999 : $project->id,
        $foreignKey => $invalidProject ? $territory->id : 999999999,
    ];

    expect(fn () => DB::transaction(fn () => DB::table($table)->insert($attributes)))
        ->toThrow(QueryException::class);
})->with('project territories')->with([false, true]);

it('exige ambas claves foráneas en las tablas intermedias', function (string $relation, string $table, string $foreignKey, string $model, bool $missingProject): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $attributes = [
        'project_id' => $missingProject ? null : $project->id,
        $foreignKey => $missingProject ? $territory->id : null,
    ];

    expect(fn () => DB::transaction(fn () => DB::table($table)->insert($attributes)))
        ->toThrow(QueryException::class);
})->with('project territories')->with([false, true]);

it('restringe el borrado físico de proyectos y territorios asociados', function (string $relation, string $table, string $foreignKey, string $model, bool $deleteProject): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $project->{$relation}()->attach($territory->id);
    $parent = $deleteProject ? $project : $territory;

    expect(fn () => DB::transaction(fn () => $parent->forceDelete()))
        ->toThrow(QueryException::class);

    $this->assertModelExists($project);
    $this->assertModelExists($territory);
    expect(DB::table($table)->where('project_id', $project->id)->count())->toBe(1);
})->with('project territories')->with([false, true]);

it('preserva las asociaciones al eliminar y restaurar lógicamente un proyecto', function (string $relation, string $table, string $foreignKey, string $model): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $project->{$relation}()->attach($territory->id);
    $pivot = DB::table($table)->where('project_id', $project->id)->sole();

    $project->delete();

    $this->assertSoftDeleted($project);
    expect(DB::table($table)->where('project_id', $project->id)->sole())->toEqual($pivot)
        ->and($territory->projects()->exists())->toBeFalse()
        ->and($territory->projects()->withTrashed()->sole()->is($project))->toBeTrue();

    $project->restore();

    expect($territory->projects()->sole()->is($project))->toBeTrue()
        ->and($project->{$relation}()->sole()->is($territory))->toBeTrue();
})->with('project territories');

it('conserva los vínculos cuando un catálogo territorial se archiva y restaura', function (string $relation, string $table, string $foreignKey, string $model): void {
    $project = Project::factory()->create();
    $territory = $model::factory()->create();
    $project->{$relation}()->attach($territory->id);

    $territory->delete();

    expect(DB::table($table)->where('project_id', $project->id)->count())->toBe(1)
        ->and($project->{$relation}()->exists())->toBeFalse()
        ->and($project->{$relation}()->withTrashed()->sole()->is($territory))->toBeTrue();

    $territory->restore();

    expect($project->{$relation}()->sole()->is($territory))->toBeTrue();
})->with('project territories');

it('limita las tablas intermedias a sus identificadores y timestamps e indexa ambos sentidos', function (string $relation, string $table, string $foreignKey): void {
    expect(Schema::getColumnListing($table))->toBe(['id', 'project_id', $foreignKey, 'created_at', 'updated_at']);

    $indexes = collect(Schema::getIndexes($table));

    expect($indexes->contains(fn (array $index): bool => $index['primary'] && $index['columns'] === ['id']))->toBeTrue()
        ->and($indexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['project_id', $foreignKey]))->toBeTrue()
        ->and($indexes->contains(fn (array $index): bool => ! $index['unique'] && $index['columns'] === [$foreignKey]))->toBeTrue();
})->with('project territories');

it('revierte y recrea las dos tablas intermedias sin alterar los proyectos ni los territorios', function (): void {
    $province = Province::factory()->create();
    $municipality = Municipality::factory()->for($province)->create();
    $project = Project::factory()->create(['main_province_id' => $province->id]);
    $tables = ['project_provinces', 'project_municipalities'];
    $migrations = [];
    $schemas = [];

    foreach ($tables as $table) {
        $paths = glob(database_path('migrations/*_create_'.$table.'_table.php'));
        expect($paths)->toHaveCount(1);
        $migrations[] = require $paths[0];
        $schemas[$table] = [
            'columns' => Schema::getColumns($table),
            'indexes' => Schema::getIndexes($table),
            'foreign_keys' => Schema::getForeignKeys($table),
        ];
    }

    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $this->assertModelExists($project);
    $this->assertModelExists($province);
    $this->assertModelExists($municipality);

    foreach ($migrations as $migration) {
        $migration->up();
    }

    foreach ($tables as $table) {
        expect([
            'columns' => Schema::getColumns($table),
            'indexes' => Schema::getIndexes($table),
            'foreign_keys' => Schema::getForeignKeys($table),
        ])->toBe($schemas[$table]);
    }

    $project->provinces()->attach($province->id);
    $project->municipalities()->attach($municipality->id);

    expect($project->fresh()->main_province_id)->toBe($province->id)
        ->and($project->provinces()->sole()->is($province))->toBeTrue()
        ->and($project->municipalities()->sole()->is($municipality))->toBeTrue();
});
