<?php

use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Province;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

dataset('investment catalogs', [
    'instituciones' => [Institution::class],
    'sectores' => [Sector::class],
    'ejes estratégicos' => [StrategicAxis::class],
    'tipos de intervención' => [InterventionType::class],
    'estados de proyecto' => [ProjectStatus::class],
]);

dataset('investment parents', [
    'institución' => ['institution_id', 'institution'],
    'sector' => ['sector_id', 'sector'],
    'eje estratégico' => ['strategic_axis_id', 'strategicAxis'],
    'tipo de intervención' => ['intervention_type_id', 'interventionType'],
    'estado' => ['project_status_id', 'projectStatus'],
    'provincia principal' => ['main_province_id', 'mainProvince'],
]);

it('crea catálogos activos y permite desactivarlos', function (string $model): void {
    expect(new $model)->active->toBeTrue();

    $catalog = $model::factory()->create();
    $inactiveCatalog = $model::factory()->inactive()->create();

    $this->assertModelExists($catalog);
    expect($catalog->fresh()->active)->toBeTrue()
        ->and($inactiveCatalog->fresh()->active)->toBeFalse()
        ->and($catalog->created_at)->not->toBeNull()
        ->and($catalog->updated_at)->not->toBeNull();
})->with('investment catalogs');

it('aplica el valor activo predeterminado también en inserciones directas', function (string $model): void {
    $attributes = $model::factory()->raw();
    unset($attributes['active']);

    $id = DB::table((new $model)->getTable())->insertGetId($attributes);

    expect($model::findOrFail($id)->active)->toBeTrue();
})->with('investment catalogs');

it('impide duplicar los identificadores únicos de los catálogos', function (string $model, string $field, string $value): void {
    $model::factory()->create([$field => $value]);

    expect(fn () => DB::transaction(fn () => $model::factory()->create([$field => $value])))
        ->toThrow(QueryException::class);
})->with([
    [Institution::class, 'code', 'GADLP'],
    [Sector::class, 'name', 'Infraestructura vial'],
    [StrategicAxis::class, 'name', 'Desarrollo territorial'],
    [InterventionType::class, 'name', 'Construcción'],
    [ProjectStatus::class, 'code', 'in_execution'],
    [ProjectStatus::class, 'name', 'En ejecución'],
]);

it('conecta todas las relaciones de los proyectos en ambos sentidos', function (): void {
    $project = Project::factory()->classified()->create();
    $project->load(['institution', 'sector', 'strategicAxis', 'interventionType', 'projectStatus', 'mainProvince']);

    foreach ([
        'institution' => Institution::class,
        'sector' => Sector::class,
        'strategicAxis' => StrategicAxis::class,
        'interventionType' => InterventionType::class,
        'projectStatus' => ProjectStatus::class,
        'mainProvince' => Province::class,
    ] as $relation => $model) {
        $parent = $project->{$relation};
        $inverse = $relation === 'mainProvince' ? 'mainProjects' : 'projects';

        expect($parent)->toBeInstanceOf($model)
            ->and($parent->{$inverse}()->sole()->is($project))->toBeTrue();
    }
});

it('impide crear proyectos con referencias inexistentes', function (string $foreignKey): void {
    $attributes = Project::factory()->raw([$foreignKey => 999999999]);

    expect(fn () => DB::transaction(fn () => Project::create($attributes)))
        ->toThrow(QueryException::class);
})->with('investment parents');

it('impide sustituir las referencias de un proyecto por padres inexistentes', function (string $foreignKey): void {
    $project = Project::factory()->classified()->create();
    $originalId = $project->{$foreignKey};

    expect(fn () => DB::transaction(fn () => $project->update([$foreignKey => 999999999])))
        ->toThrow(QueryException::class);

    expect($project->fresh()->{$foreignKey})->toBe($originalId);
})->with('investment parents');

it('exige institución y nombre para registrar un proyecto', function (string $field): void {
    $attributes = Project::factory()->raw([$field => null]);

    expect(fn () => DB::transaction(fn () => Project::create($attributes)))
        ->toThrow(QueryException::class);
})->with(['institution_id', 'name']);

it('conserva un proyecto aunque su padre sea eliminado lógicamente', function (string $foreignKey, string $relation): void {
    $project = Project::factory()->classified()->create();
    $parent = $project->{$relation};

    $parent->delete();

    $this->assertSoftDeleted($parent);
    $this->assertModelExists($project);
    expect($project->fresh()->{$foreignKey})->toBe($parent->id);

    $parent->restore();

    expect($project->fresh()->{$relation}->is($parent))->toBeTrue();
})->with('investment parents');

it('impide borrar físicamente los padres y conserva el historial incluso con proyectos eliminados', function (string $foreignKey, string $relation): void {
    $project = Project::factory()->classified()->create();
    $parent = $project->{$relation};
    $project->delete();

    expect(fn () => DB::transaction(fn () => $parent->forceDelete()))
        ->toThrow(QueryException::class);

    $this->assertSoftDeleted($project);
    $this->assertModelExists($parent);
})->with('investment parents');

it('elimina y restaura proyectos sin eliminar sus catálogos', function (): void {
    $project = Project::factory()->classified()->create();
    $institution = $project->institution;

    $project->delete();

    expect(Project::find($project->id))->toBeNull();
    $this->assertSoftDeleted($project);
    $this->assertModelExists($institution);

    $project->restore();

    expect(Project::findOrFail($project->id)->institution->is($institution))->toBeTrue();
});

it('acepta los tres semáforos normalizados', function (string $trafficLight): void {
    $status = ProjectStatus::factory()->create(['traffic_light' => $trafficLight]);

    expect($status->fresh()->traffic_light)->toBe($trafficLight);
})->with(['green', 'amber', 'red']);

it('rechaza semáforos inválidos en la base de datos', function (?string $trafficLight): void {
    expect(fn () => DB::transaction(fn () => ProjectStatus::factory()->create(['traffic_light' => $trafficLight])))
        ->toThrow(QueryException::class);
})->with(['yellow', 'GREEN', '', null]);

it('rechaza costos negativos en la base de datos', function (string $cost): void {
    expect(fn () => DB::transaction(fn () => Project::factory()->create(['total_cost_bob' => $cost])))
        ->toThrow(QueryException::class);
})->with(['-0.01', '-1000000.25']);

it('conserva exactamente los importes decimales y los serializa como cadenas', function (string $cost): void {
    $project = Project::factory()->create(['total_cost_bob' => $cost])->fresh();

    expect($project->total_cost_bob)->toBe($cost)
        ->and($project->toArray()['total_cost_bob'])->toBe($cost)
        ->and(DB::table($project->getTable())->where('id', $project->id)->value('total_cost_bob'))->toBe($cost);
})->with(['0.00', '0.10', '1234567890123456.78', '9999999999999999.99']);

it('mantiene nulos los datos que la fuente no informa', function (): void {
    $project = Project::factory()->create()->fresh();

    foreach ([
        'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id',
        'main_province_id', 'source_code', 'short_name', 'executing_unit', 'cycle_phase',
        'project_condition', 'condition_origin', 'original_status', 'territorial_scope',
        'total_cost_bob', 'funding_source', 'source_reference', 'source_year',
    ] as $field) {
        expect($project->{$field})->toBeNull();
    }

    expect($project->sector)->toBeNull()
        ->and($project->strategicAxis)->toBeNull()
        ->and($project->interventionType)->toBeNull()
        ->and($project->projectStatus)->toBeNull()
        ->and($project->mainProvince)->toBeNull();
});

it('conserva códigos de origen y admite su reutilización entre instituciones años y documentos', function (): void {
    $gadlp = Institution::factory()->create(['code' => 'GADLP']);
    $abc = Institution::factory()->create(['code' => 'ABC']);

    $projects = collect([
        [$gadlp, 2026, 'Documento A'],
        [$abc, 2026, 'Documento A'],
        [$gadlp, 2027, 'Documento A'],
        [$gadlp, 2026, 'Documento B'],
    ])->map(fn (array $source): Project => Project::factory()->for($source[0])->create([
        'source_code' => 'POA-084',
        'source_year' => $source[1],
        'source_reference' => $source[2],
    ]));

    expect($projects->pluck('id')->unique())->toHaveCount(4)
        ->and($projects->every(fn (Project $project): bool => $project->fresh()->source_code === 'POA-084'))->toBeTrue()
        ->and($gadlp->projects()->count())->toBe(3)
        ->and($abc->projects()->count())->toBe(1);

    $abcProject = Project::factory()->for($abc)->create(['source_code' => 'ABC-022']);
    expect($abcProject->fresh()->source_code)->toBe('ABC-022');
});

it('conserva metadatos de la fuente sin normalizarlos ni inferir clasificaciones', function (): void {
    $metadata = [
        'short_name' => 'Tramo principal',
        'executing_unit' => 'Unidad ejecutora de la fuente',
        'cycle_phase' => 'Preinversión',
        'project_condition' => Project::CONDITION_CONTINUING,
        'condition_origin' => Project::CONDITION_ORIGIN_DECLARED,
        'original_status' => 'En cierre técnico y administrativo según documento',
        'territorial_scope' => 'Interprovincial',
        'funding_source' => 'Según documento de origen',
        'source_reference' => 'Panel estratégico, hoja GADLP, fila 84',
        'source_year' => 2026,
    ];
    $project = Project::factory()->create($metadata)->fresh();

    foreach ($metadata as $field => $value) {
        expect($project->{$field})->toBe($value);
    }

    expect($project->project_status_id)->toBeNull();
});

it('acepta condiciones estables y su procedencia explícita', function (string $condition, string $origin): void {
    $project = Project::factory()->create([
        'project_condition' => $condition,
        'condition_origin' => $origin,
    ])->fresh();

    expect($project->project_condition)->toBe($condition)
        ->and($project->condition_origin)->toBe($origin);
})->with([Project::CONDITION_NEW, Project::CONDITION_CONTINUING])
    ->with([Project::CONDITION_ORIGIN_DECLARED, Project::CONDITION_ORIGIN_INFERRED]);

it('rechaza condiciones y procedencias fuera del vocabulario definido', function (string $field, string $value): void {
    expect(fn () => DB::transaction(fn () => Project::factory()->create([$field => $value])))
        ->toThrow(QueryException::class);
})->with([
    ['project_condition', 'unknown'],
    ['project_condition', ''],
    ['condition_origin', 'assumed'],
    ['condition_origin', ''],
]);

it('indexa los filtros de origen y las referencias sin imponer unicidad al código', function (): void {
    $indexes = Schema::getIndexes('projects');

    foreach ([
        ['institution_id', 'source_code', 'source_year'],
        ['source_code'],
        ['source_year'],
        ['sector_id'],
        ['strategic_axis_id'],
        ['intervention_type_id'],
        ['project_status_id'],
        ['main_province_id'],
    ] as $columns) {
        expect(collect($indexes)->contains(fn (array $index): bool => $index['columns'] === $columns && ! $index['unique']))->toBeTrue();
    }

    expect(collect($indexes)->where('unique', true)->pluck('columns')->all())->toBe([['id']]);
});
