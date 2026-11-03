<?php

use App\Models\Investment\Project;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('mantiene independientes la provincia principal los territorios declarados y los municipios beneficiados', function (): void {
    $mainProvince = Province::factory()->create();
    $coveredProvince = Province::factory()->create();
    $municipalProvince = Province::factory()->create();
    $municipality = Municipality::factory()->for($municipalProvince)->create();
    Municipality::factory()->for($coveredProvince)->create();
    $project = Project::factory()->create(['main_province_id' => $mainProvince->id]);

    expect($project->provinces()->exists())->toBeFalse()
        ->and($project->municipalities()->exists())->toBeFalse();

    $project->provinces()->attach($coveredProvince->id);

    expect($project->municipalities()->exists())->toBeFalse();

    $project->municipalities()->attach($municipality->id);

    expect($project->provinces()->sole()->is($coveredProvince))->toBeTrue()
        ->and($project->municipalities()->sole()->is($municipality))->toBeTrue()
        ->and($project->fresh()->mainProvince->is($mainProvince))->toBeTrue()
        ->and($mainProvince->mainProjects()->sole()->is($project))->toBeTrue()
        ->and($mainProvince->projects()->exists())->toBeFalse()
        ->and($coveredProvince->projects()->sole()->is($project))->toBeTrue()
        ->and($coveredProvince->mainProjects()->exists())->toBeFalse()
        ->and($municipalProvince->projects()->exists())->toBeFalse();
});

it('filtra por provincias asociadas sin inferirlas desde la provincia principal o los municipios', function (): void {
    $province = Province::factory()->create();
    $municipality = Municipality::factory()->for($province)->create();
    $matching = Project::factory()->create();
    $matching->provinces()->attach($province->id);
    Project::factory()->create(['main_province_id' => $province->id]);
    $municipalityOnly = Project::factory()->create();
    $municipalityOnly->municipalities()->attach($municipality->id);
    Project::factory()->create();

    expect(Project::inProvince($province->id)->sole()->is($matching))->toBeTrue()
        ->and(Project::inProvince(999999999)->exists())->toBeFalse();
});

it('filtra por un municipio compartido por varios proyectos sin incorporar los demás municipios de su provincia', function (): void {
    $province = Province::factory()->create();
    $municipality = Municipality::factory()->for($province)->create();
    $neighbor = Municipality::factory()->for($province)->create();
    $projects = Project::factory()->count(2)->create();

    foreach ($projects as $project) {
        $project->municipalities()->attach($municipality->id);
    }

    $outside = Project::factory()->create();
    $outside->municipalities()->attach($neighbor->id);
    $outside->provinces()->attach($province->id);

    expect(Project::inMunicipality($municipality->id)->orderBy('id')->get()->modelKeys())->toBe($projects->modelKeys())
        ->and(Project::inMunicipality(999999999)->exists())->toBeFalse();
});

it('consulta proyectos de municipios pertenecientes a distintas regiones mediante su región vigente', function (): void {
    $firstRegion = Region::factory()->create();
    $secondRegion = Region::factory()->create();
    $firstMunicipalities = Municipality::factory()->count(2)->for($firstRegion)->create();
    $secondMunicipality = Municipality::factory()->for($secondRegion)->create();
    $shared = Project::factory()->create();
    $shared->municipalities()->attach([...$firstMunicipalities->modelKeys(), $secondMunicipality->id]);
    $secondOnly = Project::factory()->create();
    $secondOnly->municipalities()->attach($secondMunicipality->id);

    expect(Project::inCurrentRegion($firstRegion->id)->sole()->is($shared))->toBeTrue()
        ->and(Project::inCurrentRegion($secondRegion->id)->orderBy('id')->get()->modelKeys())->toBe([$shared->id, $secondOnly->id])
        ->and(Project::inCurrentRegion(999999999)->exists())->toBeFalse()
        ->and($firstMunicipalities[0]->regionMemberships()->exists())->toBeFalse();
});

it('resuelve regiones históricas sin cambiar las asociaciones cuando un municipio cambia de región', function (): void {
    $firstRegion = Region::factory()->create();
    $secondRegion = Region::factory()->create();
    $moving = Municipality::factory()->for($firstRegion)->create();
    $stationary = Municipality::factory()->for($firstRegion)->create();
    $movingOnly = Project::factory()->create();
    $movingOnly->municipalities()->attach($moving->id);
    $shared = Project::factory()->create();
    $shared->municipalities()->attach([$moving->id, $stationary->id]);
    $memberships = app(MunicipalityRegionMembershipService::class);
    $memberships->initialize($moving, '2020-01-01');
    $memberships->initialize($stationary, '2020-01-01');
    $pivotBefore = DB::table('project_municipalities')->orderBy('id')->get()->toArray();

    $memberships->changeRegion($moving, $secondRegion->id, [
        'valid_from' => '2021-01-01',
        'agreement_reference' => 'RES-TEST-CAMBIO-REGIONAL',
    ]);

    expect(DB::table('project_municipalities')->orderBy('id')->get()->toArray())->toEqual($pivotBefore)
        ->and(Project::inCurrentRegion($firstRegion->id)->sole()->is($shared))->toBeTrue()
        ->and(Project::inCurrentRegion($secondRegion->id)->count())->toBe(2)
        ->and(Project::inRegionOn($firstRegion->id, '2019-12-31')->exists())->toBeFalse()
        ->and(Project::inRegionOn($firstRegion->id, '2020-01-01')->count())->toBe(2)
        ->and(Project::inRegionOn($firstRegion->id, '2020-12-31')->count())->toBe(2)
        ->and(Project::inRegionOn($secondRegion->id, '2020-12-31')->exists())->toBeFalse()
        ->and(Project::inRegionOn($firstRegion->id, '2021-01-01')->sole()->is($shared))->toBeTrue()
        ->and(Project::inRegionOn($secondRegion->id, '2021-01-01')->orderBy('id')->get()->modelKeys())->toBe([$movingOnly->id, $shared->id]);
});

it('cuenta proyectos por su identidad y suma una sola vez sus costos aunque dos proyectos tengan el mismo importe', function (): void {
    $region = Region::factory()->create();
    $province = Province::factory()->create();
    $municipalities = Municipality::factory()->count(2)->for($region)->for($province)->create();
    $otherMunicipality = Municipality::factory()->create();
    $projects = Project::factory()->count(2)->create(['total_cost_bob' => '100.10']);
    $unknownCost = Project::factory()->create(['total_cost_bob' => null]);

    foreach ([...$projects, $unknownCost] as $project) {
        $project->municipalities()->attach($municipalities->modelKeys());
        $project->provinces()->attach($province->id);
    }

    $projects[0]->municipalities()->attach($otherMunicipality->id);
    $outside = Project::factory()->create(['total_cost_bob' => '999.99']);
    $outside->municipalities()->attach($otherMunicipality->id);

    foreach ($municipalities as $municipality) {
        app(MunicipalityRegionMembershipService::class)->initialize($municipality, '2020-01-01');
    }

    foreach ([
        Project::inProvince($province->id),
        Project::inMunicipality($municipalities[0]->id),
        Project::inCurrentRegion($region->id),
        Project::inRegionOn($region->id, '2021-01-01'),
    ] as $query) {
        expect((clone $query)->count())->toBe(3)
            ->and((clone $query)->sum('total_cost_bob'))->toBe('200.20')
            ->and((clone $query)->orderBy('id')->get()->modelKeys())->toBe([...$projects->modelKeys(), $unknownCost->id]);
    }

    $projects[1]->delete();

    expect(Project::inCurrentRegion($region->id)->count())->toBe(2)
        ->and(Project::inCurrentRegion($region->id)->sum('total_cost_bob'))->toBe('100.10');
});

it('carga los territorios de varios proyectos sin consultas adicionales por cada proyecto', function (): void {
    $projects = Project::factory()->count(3)
        ->hasAttached(Province::factory()->count(2), [], 'provinces')
        ->hasAttached(Municipality::factory()->count(2), [], 'municipalities')
        ->create();

    DB::enableQueryLog();

    try {
        $loaded = Project::with(['provinces', 'municipalities'])->whereKey($projects->modelKeys())->get();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($queries)->toHaveCount(3);

    foreach ($loaded as $project) {
        expect($project->relationLoaded('provinces'))->toBeTrue()
            ->and($project->relationLoaded('municipalities'))->toBeTrue()
            ->and($project->provinces)->toHaveCount(2)
            ->and($project->municipalities)->toHaveCount(2);
    }
});
