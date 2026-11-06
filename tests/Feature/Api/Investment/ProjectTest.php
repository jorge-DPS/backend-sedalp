<?php

use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Models\User;
use App\Services\Investment\ProjectBudgetService;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->institution = Institution::factory()->create();
    $this->province = Province::factory()->create();
    $this->otherProvince = Province::factory()->create();
    $this->region = Region::factory()->create();
    $this->otherRegion = Region::factory()->create();
    $this->municipalities = Municipality::factory()->count(3)->create(['province_id' => $this->province->id, 'region_id' => $this->region->id]);
    $this->otherMunicipality = Municipality::factory()->create(['province_id' => $this->otherProvince->id, 'region_id' => $this->otherRegion->id]);
    $this->payload = [
        'institution_id' => $this->institution->id,
        'sector_id' => Sector::factory()->create()->id,
        'strategic_axis_id' => StrategicAxis::factory()->create()->id,
        'intervention_type_id' => InterventionType::factory()->create()->id,
        'project_status_id' => ProjectStatus::factory()->create()->id,
        'main_province_id' => $this->otherProvince->id,
        'name' => 'Mejora del acceso rural', 'source_code' => '001-SIMRED', 'source_year' => 2025,
        'total_cost_bob' => '1234567890123456.78',
        'project_condition' => 'new', 'condition_origin' => 'declared',
        'province_ids' => [$this->province->id],
        'municipality_ids' => [...$this->municipalities->modelKeys(), $this->otherMunicipality->id],
    ];
});

it('registra proyectos multiterritoriales con precisión y permite repetir códigos de fuente', function (): void {
    $response = $this->postJson('/api/admin/investment/projects', [...$this->payload, 'id' => 999999999])
        ->assertCreated()->assertJsonPath('data.source_code', '001-SIMRED')
        ->assertJsonPath('data.total_cost_bob', '1234567890123456.78')
        ->assertJsonPath('data.institution.id', $this->institution->id)
        ->assertJsonPath('data.main_province.id', $this->otherProvince->id)
        ->assertJsonCount(1, 'data.provinces')->assertJsonCount(4, 'data.municipalities');
    $project = Project::findOrFail($response->json('data.id'));

    expect($project->getKey())->not->toBe(999999999)
        ->and($project->provinces()->get()->modelKeys())->toBe([$this->province->id])
        ->and($project->municipalities()->count())->toBe(4)
        ->and($project->budgets()->exists())->toBeFalse();

    $this->postJson('/api/admin/investment/projects', $this->payload)->assertCreated();
    expect(Project::where('source_code', '001-SIMRED')->count())->toBe(2);
});

it('conserva relaciones omitidas y solo retira las solicitadas con arreglos vacíos', function (): void {
    $id = $this->postJson('/api/admin/investment/projects', $this->payload)->assertCreated()->json('data.id');

    $this->patchJson("/api/admin/investment/projects/{$id}", ['short_name' => 'Acceso'])
        ->assertOk()->assertJsonCount(1, 'data.provinces')->assertJsonCount(4, 'data.municipalities');
    $this->patchJson("/api/admin/investment/projects/{$id}", ['province_ids' => [], 'municipality_ids' => [$this->otherMunicipality->id]])
        ->assertOk()->assertJsonCount(0, 'data.provinces')->assertJsonCount(1, 'data.municipalities')
        ->assertJsonPath('data.main_province_id', $this->otherProvince->id);
    $this->putJson("/api/admin/investment/projects/{$id}", ['municipality_ids' => []])
        ->assertOk()->assertJsonCount(0, 'data.municipalities');

    expect(Project::findOrFail($id)->name)->toBe($this->payload['name']);
});

it('valida los campos y evita territorios duplicados o inexistentes', function (string $case, string $error): void {
    $changes = match ($case) {
        'name' => ['name' => null],
        'institution' => ['institution_id' => 999999999],
        'province' => ['province_ids' => [999999999]],
        'municipality' => ['municipality_ids' => [999999999]],
        'duplicate' => ['municipality_ids' => [$this->otherMunicipality->id, $this->otherMunicipality->id]],
        'duplicate_province' => ['province_ids' => [$this->province->id, $this->province->id]],
        'null_array' => ['province_ids' => null],
        'condition' => ['project_condition' => 'unknown'],
        'origin' => ['condition_origin' => 'unknown'],
        'float' => ['total_cost_bob' => 10.25],
        'negative' => ['total_cost_bob' => '-0.01'],
        'precision' => ['total_cost_bob' => '0.001'],
        'overflow' => ['total_cost_bob' => '10000000000000000.00'],
        'nested_budget' => ['budgets' => [['amount_bob' => '10.00']]],
    };

    $this->postJson('/api/admin/investment/projects', [...$this->payload, ...$changes])
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    expect(Project::count())->toBe(0);
})->with([
    ['name', 'name'], ['institution', 'institution_id'], ['province', 'province_ids.0'], ['municipality', 'municipality_ids.0'],
    ['duplicate', 'municipality_ids.0'], ['duplicate_province', 'province_ids.0'], ['null_array', 'province_ids'],
    ['condition', 'project_condition'], ['origin', 'condition_origin'], ['float', 'total_cost_bob'],
    ['negative', 'total_cost_bob'], ['precision', 'total_cost_bob'], ['overflow', 'total_cost_bob'], ['nested_budget', 'budgets'],
]);

it('rechaza asignaciones a catálogos y territorios inactivos o eliminados', function (string $field, bool $deleted): void {
    $record = match ($field) {
        'institution_id' => $this->institution,
        'sector_id' => Sector::findOrFail($this->payload['sector_id']),
        'province_ids' => $this->province,
        'municipality_ids' => $this->otherMunicipality,
    };
    $deleted ? $record->delete() : $record->update(['active' => false]);
    $this->postJson('/api/admin/investment/projects', $this->payload)->assertUnprocessable();
})->with(['institution_id', 'sector_id', 'province_ids', 'municipality_ids'])->with([false, true]);

it('filtra por territorios sin duplicar y sin inferir beneficiarios de la provincia principal', function (): void {
    $id = $this->postJson('/api/admin/investment/projects', $this->payload)->assertCreated()->json('data.id');
    Project::factory()->create(['main_province_id' => $this->province->id]);

    foreach ([
        ['region_id' => $this->region->id],
        ['region_id' => $this->otherRegion->id],
        ['province_id' => $this->province->id],
        ['municipality_id' => $this->municipalities[0]->id],
        [
            ...Arr::only($this->payload, ['institution_id', 'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id', 'source_year']),
            'region_id' => $this->region->id, 'province_id' => $this->province->id,
            'municipality_id' => $this->municipalities[1]->id, 'search' => '001-simred',
        ],
    ] as $filters) {
        $this->getJson('/api/admin/investment/projects?'.http_build_query($filters))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    }

    $this->getJson('/api/admin/investment/projects?province_id='.$this->otherProvince->id)
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/admin/investment/projects?source_year=2027')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/admin/investment/projects?search=acceso%20rural')->assertOk()->assertJsonCount(1, 'data');
});

it('aplica cada filtro de clasificación de manera independiente', function (string $field): void {
    $target = Project::factory()->create(Arr::except($this->payload, ['province_ids', 'municipality_ids']));
    Project::factory()->classified()->create(['source_year' => 2026]);
    $this->getJson('/api/admin/investment/projects?'.http_build_query([$field => $this->payload[$field]]))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
})->with(['institution_id', 'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id', 'source_year']);

it('consulta pertenencia regional actual o histórica con fecha explícita', function (): void {
    $municipality = $this->municipalities[0];
    $memberships = app(MunicipalityRegionMembershipService::class);
    $memberships->initialize($municipality, '2020-01-01');
    $memberships->changeRegion($municipality, $this->otherRegion->id, ['valid_from' => '2024-01-01', 'agreement_reference' => 'RES-2024']);
    $project = Project::factory()->create(['source_year' => 2025]);
    $project->municipalities()->attach($municipality);

    foreach ([
        [['region_id' => $this->region->id, 'region_date' => '2023-12-31'], 1],
        [['region_id' => $this->region->id, 'region_date' => '2024-01-01'], 0],
        [['region_id' => $this->otherRegion->id], 1],
        [['region_id' => $this->region->id], 0],
    ] as [$filters, $count]) {
        $this->getJson('/api/admin/investment/projects?'.http_build_query($filters))
            ->assertOk()->assertJsonPath('meta.total', $count);
    }
});

it('valida paginación filtros y ordenamiento seguro', function (array $query, string $field): void {
    $this->getJson('/api/admin/investment/projects?'.http_build_query($query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['page' => 0], 'page'], [['per_page' => 101], 'per_page'],
    [['sort_by' => 'name;DROP TABLE projects'], 'sort_by'], [['sort_direction' => 'random()'], 'sort_direction'],
    [['region_date' => '2025-01-01'], 'region_id'], [['region_date' => '2025-02-30'], 'region_date'],
    [['province_id' => 999999999], 'province_id'], [['municipality_id' => 999999999], 'municipality_id'],
]);

it('pagina proyectos con orden estable y relaciones cargadas', function (): void {
    Project::factory()->count(3)->create(Arr::except($this->payload, ['province_ids', 'municipality_ids']));
    $response = $this->getJson('/api/admin/investment/projects?per_page=2&sort_by=id&sort_direction=asc')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/admin/investment/projects?per_page=2&page=2&sort_by=id&sort_direction=asc')
        ->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.id'))->toBeLessThan($response->json('data.1.id'));
});

it('protege la coherencia de las fuentes actuales e históricas al editar el proyecto', function (): void {
    $source = InvestmentSource::factory()->create(['institution_id' => $this->institution->id, 'file_path' => 'private/secret.pdf']);
    $foreign = InvestmentSource::factory()->create();
    $id = $this->postJson('/api/admin/investment/projects', [...$this->payload, 'primary_investment_source_id' => $source->id])
        ->assertCreated()->assertJsonMissingPath('data.primary_investment_source.file_path')->json('data.id');
    $this->patchJson("/api/admin/investment/projects/{$id}", ['primary_investment_source_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_investment_source_id');

    $project = Project::findOrFail($id);
    $budget = ProjectBudget::factory()->for($project)->reported('100.00')->create(['investment_source_id' => $source->id]);
    app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '100.00', 'availability_status' => 'reported', 'investment_source_id' => null]);
    $this->patchJson("/api/admin/investment/projects/{$id}", [
        'primary_investment_source_id' => null, 'institution_id' => $foreign->institution_id, 'province_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('institution_id');

    expect($project->fresh()->institution_id)->toBe($this->institution->id)
        ->and($project->provinces()->count())->toBe(1);
});

it('elimina lógicamente sin perder territorios presupuestos ni historial', function (): void {
    $this->user->givePermissionTo('investment.projects.delete');
    $id = $this->postJson('/api/admin/investment/projects', $this->payload)->assertCreated()->json('data.id');
    $project = Project::findOrFail($id);
    $budget = ProjectBudget::factory()->for($project)->create();
    app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '100.00', 'availability_status' => 'reported']);

    $this->deleteJson("/api/admin/investment/projects/{$id}")->assertNoContent();
    $this->assertSoftDeleted($project);
    expect(Project::withTrashed()->findOrFail($id)->municipalities()->count())->toBe(4)
        ->and($budget->revisions()->count())->toBe(1);
    $this->getJson("/api/admin/investment/projects/{$id}")->assertNotFound();
    $this->getJson("/api/admin/investment/projects/{$id}/budgets")->assertNotFound();
    $this->getJson('/api/admin/investment/projects')->assertOk()->assertJsonCount(0, 'data');
});

it('revierte el proyecto y conserva sus relaciones cuando falla una actualización', function (): void {
    $id = $this->postJson('/api/admin/investment/projects', $this->payload)->assertCreated()->json('data.id');
    $event = 'eloquent.updated: '.Project::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo simulado después de escribir el proyecto.');
    });

    try {
        $this->patchJson("/api/admin/investment/projects/{$id}", ['name' => 'Cambio fallido', 'province_ids' => []])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }

    expect(Project::findOrFail($id)->name)->toBe($this->payload['name'])
        ->and(Project::findOrFail($id)->provinces()->count())->toBe(1);
});

it('entrega únicamente catálogos activos para formularios', function (): void {
    Institution::factory()->create(['active' => false]);
    $deleted = Sector::factory()->create();
    $deleted->delete();

    $this->getJson('/api/admin/investment/catalogs')->assertOk()
        ->assertJsonCount(1, 'data.institutions')->assertJsonCount(1, 'data.sectors')
        ->assertJsonStructure(['data' => ['institutions', 'sectors', 'strategic_axes', 'intervention_types', 'project_statuses']]);
});

it('rechaza una fuente principal de otra institución antes de insertar el proyecto', function (): void {
    $source = InvestmentSource::factory()->create();
    $this->postJson('/api/admin/investment/projects', [...$this->payload, 'primary_investment_source_id' => $source->id])
        ->assertUnprocessable()->assertJsonValidationErrors('primary_investment_source_id');

    expect(Project::count())->toBe(0);
});

it('revierte el proyecto y ambas tablas intermedias si falla la sincronización territorial', function (): void {
    $connection = DB::connection();
    $dispatcher = $connection->getEventDispatcher();
    $connection->setEventDispatcher(clone $dispatcher);
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'insert into "project_municipalities"')) {
            throw new RuntimeException('Fallo simulado después de escribir los territorios.');
        }
    });

    try {
        $this->postJson('/api/admin/investment/projects', $this->payload)->assertInternalServerError();
    } finally {
        $connection->setEventDispatcher($dispatcher);
    }

    expect(Project::count())->toBe(0)
        ->and(DB::table('project_provinces')->count())->toBe(0)
        ->and(DB::table('project_municipalities')->count())->toBe(0);
});
