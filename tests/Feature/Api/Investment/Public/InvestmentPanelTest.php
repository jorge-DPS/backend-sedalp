<?php

use App\Models\Investment\Institution;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Services\Investment\ProjectBudgetService;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

it('responde sin información publicada sin inventar importes ni instituciones', function (): void {
    Project::factory()->create();
    $this->getJson('/api/public/investment/institutions')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/public/investment/projects')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/public/investment/dashboard?fiscal_year=2027')->assertOk()
        ->assertJsonPath('data.project_count', 0)->assertJsonPath('data.has_registered_data', false)
        ->assertJsonPath('data.total_cost.known_amount_bob', null)
        ->assertJsonPath('data.annual_budget.known_amount_bob', null)
        ->assertJsonPath('meta.coverage', 'available_published_records');
    $this->getJson('/api/public/investment/distributions')->assertOk()->assertJsonCount(0, 'data.sectors');
    foreach (['regions', 'provinces', 'municipalities'] as $type) {
        $this->getJson('/api/public/investment/territories/'.$type)->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('unassigned.project_count', 0);
    }
});

it('funciona con cada institución independientemente y con ambas', function (array $codes): void {
    foreach ($codes as $code) {
        $institution = Institution::factory()->create(['code' => $code]);
        Project::factory()->published()->count(2)->create(['institution_id' => $institution->id, 'total_cost_bob' => '100.25']);
        $this->getJson('/api/public/investment/dashboard?institution_id='.$institution->id)
            ->assertOk()->assertJsonPath('data.project_count', 2)->assertJsonPath('data.total_cost.known_amount_bob', '200.50');
    }
    $this->getJson('/api/public/investment/institutions')->assertOk()->assertJsonCount(count($codes), 'data');
    $this->getJson('/api/public/investment/dashboard')->assertOk()->assertJsonPath('data.project_count', count($codes) * 2);
})->with([[['GADLP']], [['ABC']], [['GADLP', 'ABC']]]);

it('distingue costo presupuesto cero desconocido y ausencia sin sumar revisiones', function (): void {
    $institution = Institution::factory()->create();
    $projects = collect(['1234567890123456.78', '1234567890123456.78', '0.00', null, null])
        ->map(fn (?string $cost): Project => Project::factory()->published()->create([
            'institution_id' => $institution->id, 'total_cost_bob' => $cost, 'source_year' => 2025,
        ]));
    $budget = ProjectBudget::factory()->for($projects[0])->reported('100.00')->create(['fiscal_year' => 2027]);
    app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '200.00', 'availability_status' => 'reported', 'reason' => 'Privado']);
    ProjectBudget::factory()->for($projects[1])->reported('200.00')->create(['fiscal_year' => 2027]);
    ProjectBudget::factory()->for($projects[2])->reported('0.00')->create(['fiscal_year' => 2027]);
    ProjectBudget::factory()->for($projects[3])->create(['fiscal_year' => 2027]);
    ProjectBudget::factory()->for($projects[0])->reported('99.00')->create(['fiscal_year' => 2028]);

    $this->getJson('/api/public/investment/dashboard?fiscal_year=2027&source_year=2025')->assertOk()
        ->assertJsonPath('data.project_count', 5)
        ->assertJsonPath('data.total_cost.known_amount_bob', '2469135780246913.56')
        ->assertJsonPath('data.total_cost.reported_positive_count', 2)
        ->assertJsonPath('data.total_cost.reported_zero_count', 1)->assertJsonPath('data.total_cost.unknown_count', 2)
        ->assertJsonPath('data.annual_budget.known_amount_bob', '400.00')
        ->assertJsonPath('data.annual_budget.recorded_count', 4)
        ->assertJsonPath('data.annual_budget.reported_positive_count', 2)
        ->assertJsonPath('data.annual_budget.reported_zero_count', 1)
        ->assertJsonPath('data.annual_budget.unavailable_count', 1)
        ->assertJsonPath('data.annual_budget.missing_record_count', 1);
    $this->getJson('/api/public/investment/dashboard?fiscal_year=2028')->assertOk()
        ->assertJsonPath('data.project_count', 5)->assertJsonPath('data.annual_budget.known_amount_bob', '99.00')
        ->assertJsonPath('data.annual_budget.missing_record_count', 4);
    $this->getJson('/api/public/investment/dashboard?source_year=2027')->assertOk()->assertJsonPath('data.project_count', 0);
    $this->getJson('/api/public/investment/dashboard')->assertOk()->assertJsonPath('data.annual_budget', null);
    foreach ([[$projects[2], true, 'reported', '0.00'], [$projects[3], true, 'not_available', null], [$projects[4], false, null, null]] as [$project, $recorded, $status, $amount]) {
        $this->getJson('/api/public/investment/projects/'.$project->id.'?fiscal_year=2027')->assertOk()
            ->assertJsonPath('data.annual_budget.recorded', $recorded)
            ->assertJsonPath('data.annual_budget.availability_status', $status)
            ->assertJsonPath('data.annual_budget.amount_bob', $amount);
    }
});

it('cuenta cada identidad una vez por conjunto aunque comparta territorios e importe', function (): void {
    $region = Region::factory()->create();
    $province = Province::factory()->create();
    $otherProvince = Province::factory()->create();
    $municipalities = Municipality::factory()->count(3)->create(['region_id' => $region->id, 'province_id' => $province->id]);
    $municipalities->push(Municipality::factory()->create(['province_id' => $otherProvince->id]));
    $projects = Project::factory()->published()->classified()->count(2)->create([
        'total_cost_bob' => '50.00', 'main_province_id' => $otherProvince->id,
        'project_condition' => 'new', 'condition_origin' => 'declared',
    ]);
    foreach ($projects as $project) {
        $project->municipalities()->attach($municipalities->modelKeys());
        $project->provinces()->attach([$province->id, $otherProvince->id]);
        ProjectBudget::factory()->for($project)->reported('10.00')->create(['fiscal_year' => 2027]);
    }
    $unassigned = Project::factory()->published()->create(['total_cost_bob' => '5.00', 'main_province_id' => $province->id]);
    foreach (['region_id' => $region->id, 'province_id' => $province->id, 'municipality_id' => $municipalities[0]->id] as $filter => $id) {
        $this->getJson('/api/public/investment/dashboard?fiscal_year=2027&'.$filter.'='.$id)->assertOk()
            ->assertJsonPath('data.project_count', 2)->assertJsonPath('data.total_cost.known_amount_bob', '100.00')
            ->assertJsonPath('data.annual_budget.known_amount_bob', '20.00')
            ->assertJsonPath('data.conditions.new_count', 2);
        $this->getJson('/api/public/investment/projects?'.$filter.'='.$id)->assertOk()->assertJsonPath('meta.total', 2);
    }
    foreach (['regions' => 2, 'provinces' => 2, 'municipalities' => 4] as $type => $count) {
        $response = $this->getJson('/api/public/investment/territories/'.$type.'?fiscal_year=2027')->assertOk()
            ->assertJsonCount($count, 'data')->assertJsonPath('unassigned.project_count', 1)
            ->assertJsonPath('unassigned.total_cost.known_amount_bob', '5.00')
            ->assertJsonPath('meta.territorial_totals_are_additive', false);
        foreach ($response->json('data') as $row) {
            expect($row['metrics']['project_count'])->toBe(2)
                ->and($row['metrics']['total_cost']['known_amount_bob'])->toBe('100.00')
                ->and($row['metrics']['annual_budget']['known_amount_bob'])->toBe('20.00');
        }
    }
    $this->getJson('/api/public/investment/dashboard?fiscal_year=2027')->assertOk()
        ->assertJsonPath('data.project_count', 3)->assertJsonPath('data.total_cost.known_amount_bob', '105.00');
    $this->getJson('/api/public/investment/distributions?region_id='.$region->id.'&fiscal_year=2027')->assertOk()
        ->assertJsonPath('data.institutions.0.metrics.project_count', 1);
});

it('respeta fechas regionales con límite exclusivo y sin inferir región de provincia', function (): void {
    $municipality = Municipality::factory()->create();
    $oldRegion = $municipality->region_id;
    $newRegion = Region::factory()->create();
    $history = app(MunicipalityRegionMembershipService::class);
    $history->initialize($municipality, '2020-01-01');
    $history->changeRegion($municipality, $newRegion->id, ['valid_from' => '2024-01-01', 'agreement_reference' => 'Convenio']);
    $project = Project::factory()->published()->create();
    $project->municipalities()->attach($municipality);
    Project::factory()->published()->create(['main_province_id' => $municipality->province_id]);
    foreach ([[$oldRegion, '2023-12-31', 1], [$oldRegion, '2024-01-01', 0], [$newRegion->id, '2024-01-01', 1], [$oldRegion, '2019-12-31', 0]] as [$id, $date, $count]) {
        $this->getJson('/api/public/investment/dashboard?region_id='.$id.'&region_date='.$date)
            ->assertOk()->assertJsonPath('data.project_count', $count);
    }
    $this->getJson('/api/public/investment/territories/regions?region_date=2023-12-31')->assertOk()
        ->assertJsonPath('data.0.territory.id', $oldRegion)->assertJsonPath('unassigned.project_count', 1);
    $this->getJson('/api/public/investment/territories/regions')->assertOk()
        ->assertJsonPath('data.0.territory.id', $newRegion->id);
});

it('aplica filtros combinados clasificaciones y búsqueda sin revelar referencias privadas', function (): void {
    $project = Project::factory()->published()->classified()->create([
        'name' => 'Camino rural', 'source_code' => 'CAM-001', 'source_year' => 2025,
        'source_reference' => 'Documento privado', 'funding_source' => 'Dato interno',
        'project_condition' => 'continuing', 'condition_origin' => 'inferred',
    ]);
    Project::factory()->published()->create();
    $filters = $project->only(['institution_id', 'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id', 'source_year']);
    $filters['search'] = 'cam-001';
    $query = http_build_query($filters);
    $this->getJson('/api/public/investment/projects?'.$query)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $project->id)->assertJsonMissingPath('data.0.source_reference')
        ->assertJsonMissingPath('data.0.funding_source')->assertJsonMissingPath('data.0.primary_investment_source');
    $response = $this->getJson('/api/public/investment/distributions?'.$query)->assertOk();
    foreach (['institutions', 'sectors', 'strategic_axes', 'intervention_types', 'project_statuses'] as $dimension) {
        $response->assertJsonCount(1, 'data.'.$dimension)->assertJsonPath('data.'.$dimension.'.0.metrics.project_count', 1)
            ->assertJsonPath('data.'.$dimension.'.0.metrics.conditions.continuing_count', 1);
    }
    $project->institution->delete();
    $this->getJson('/api/public/investment/institutions')->assertOk()->assertJsonCount(2, 'data')->assertJsonFragment([
        'id' => $project->institution_id, 'code' => $project->institution->code,
        'name' => $project->institution->name, 'projects_count' => 1,
    ]);
    $this->getJson('/api/public/investment/projects?'.$query)->assertOk()->assertJsonCount(1, 'data');
});

it('excluye proyectos privados futuros y eliminados de todos los resultados', function (): void {
    $projects = collect([
        Project::factory()->create(),
        Project::factory()->create(['published_at' => now()->addDay()]),
        Project::factory()->published()->create(),
    ]);
    $projects[2]->delete();
    foreach ($projects as $project) {
        $this->getJson('/api/public/investment/projects/'.$project->id)->assertNotFound();
    }
    $this->getJson('/api/public/investment/dashboard?search=%')->assertOk()->assertJsonPath('data.project_count', 0);
    $this->getJson('/api/public/investment/institutions')->assertOk()->assertJsonCount(0, 'data');
});

it('pagina sin límites del Excel y sin permitir mutaciones públicas', function (): void {
    $institution = Institution::factory()->create();
    Project::factory()->published()->count(41)->create(['institution_id' => $institution->id]);
    $this->getJson('/api/public/investment/projects?per_page=40&sort_by=id')->assertOk()
        ->assertJsonCount(40, 'data')->assertJsonPath('meta.total', 41);
    $this->getJson('/api/public/investment/projects?per_page=40&page=2&sort_by=id')->assertOk()->assertJsonCount(1, 'data');
    $this->postJson('/api/public/investment/projects', [])->assertMethodNotAllowed();
    $this->getJson('/api/public/investment/institutions?per_page=51')->assertUnprocessable();
});

it('mantiene constantes las consultas al cargar relaciones y presupuestos de más proyectos', function (): void {
    $project = Project::factory()->published()->classified()->create();
    $municipality = Municipality::factory()->create();
    $project->municipalities()->attach($municipality);
    $project->provinces()->attach($municipality->province_id);
    ProjectBudget::factory()->for($project)->reported()->create(['fiscal_year' => 2027]);
    DB::enableQueryLog();
    try {
        DB::flushQueryLog();
        $this->getJson('/api/public/investment/projects?fiscal_year=2027')->assertOk()->assertJsonCount(1, 'data');
        $singleCount = count(DB::getQueryLog());
        $more = Project::factory()->published()->count(9)->create($project->only([
            'institution_id', 'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id', 'main_province_id',
        ]));
        foreach ($more as $other) {
            $other->municipalities()->attach($municipality);
            $other->provinces()->attach($municipality->province_id);
            ProjectBudget::factory()->for($other)->reported()->create(['fiscal_year' => 2027]);
        }
        DB::flushQueryLog();
        $this->getJson('/api/public/investment/projects?fiscal_year=2027')->assertOk()->assertJsonCount(10, 'data');
        expect(count(DB::getQueryLog()))->toBe($singleCount);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('valida filtros públicos y evita ordenamiento SQL arbitrario', function (array $query, string $field): void {
    $this->getJson('/api/public/investment/projects?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['per_page' => 51], 'per_page'], [['page' => 0], 'page'], [['fiscal_year' => 1800], 'fiscal_year'],
    [['source_year' => 2101], 'source_year'], [['institution_id' => 999999999], 'institution_id'],
    [['sort_by' => 'name;drop table projects'], 'sort_by'], [['sort_direction' => 'random()'], 'sort_direction'],
    [['region_date' => '2025-02-30'], 'region_date'],
]);

it('limita solicitudes públicas de inversiones', function (): void {
    RateLimiter::for('public-api', fn (): Limit => Limit::perMinute(2)->by('investment-panel-test'));
    $this->getJson('/api/public/investment/dashboard')->assertOk();
    $this->getJson('/api/public/investment/dashboard')->assertOk();
    $this->getJson('/api/public/investment/dashboard')->assertTooManyRequests();
});
