<?php

use App\Enums\Territory\MembershipOriginType;
use App\Models\Territory\Municipality;
use App\Models\Territory\MunicipalityRegionMembership;
use App\Models\Territory\Region;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('relaciona y convierte las fechas y la procedencia del historial regional', function (): void {
    $membership = MunicipalityRegionMembership::factory()->create(['valid_from' => '2020-01-01']);

    expect($membership->fresh()->origin_type)->toBe(MembershipOriginType::BASELINE)
        ->and($membership->valid_from->toDateString())->toBe('2020-01-01')
        ->and($membership->valid_to)->toBeNull()
        ->and($membership->municipality->regionMemberships()->sole()->is($membership))->toBeTrue()
        ->and($membership->region->municipalityMemberships()->sole()->is($membership))->toBeTrue();
});

it('mantiene accesibles los padres eliminados lógicamente sin eliminar su historial', function (string $relation): void {
    $membership = MunicipalityRegionMembership::factory()->create();
    $parent = $membership->{$relation};

    $parent->delete();

    $this->assertModelExists($membership);
    expect($membership->fresh()->{$relation}->is($parent))->toBeTrue()
        ->and($membership->fresh()->{$relation}->trashed())->toBeTrue();
})->with(['municipality', 'region']);

it('impide eliminar físicamente los padres de un registro histórico', function (string $relation): void {
    $membership = MunicipalityRegionMembership::factory()->create();

    expect(fn () => DB::transaction(fn () => $membership->{$relation}->forceDelete()))
        ->toThrow(QueryException::class);

    $this->assertModelExists($membership);
})->with(['municipality', 'region']);

it('rechaza claves foráneas inexistentes en el historial regional', function (string $foreignKey): void {
    $municipality = Municipality::factory()->create();
    $attributes = MunicipalityRegionMembership::factory()->raw([
        'municipality_id' => $municipality->id,
        'region_id' => $municipality->region_id,
        'origin_type' => 'baseline',
        $foreignKey => 999999999,
    ]);

    expect(fn () => DB::transaction(fn () => DB::table((new MunicipalityRegionMembership)->getTable())->insert($attributes)))
        ->toThrow(QueryException::class);
})->with(['municipality_id', 'region_id']);

it('exige los campos esenciales del historial en PostgreSQL', function (string $field): void {
    $municipality = Municipality::factory()->create();
    $attributes = MunicipalityRegionMembership::factory()->raw([
        'municipality_id' => $municipality->id,
        'region_id' => $municipality->region_id,
        'origin_type' => 'baseline',
        $field => null,
    ]);

    expect(fn () => DB::transaction(fn () => DB::table((new MunicipalityRegionMembership)->getTable())->insert($attributes)))
        ->toThrow(QueryException::class);
})->with(['municipality_id', 'region_id', 'valid_from', 'origin_type']);

it('rechaza intervalos vacíos o con fin anterior al inicio', function (string $validTo): void {
    expect(fn () => DB::transaction(fn () => MunicipalityRegionMembership::factory()->create([
        'valid_from' => '2020-01-01',
        'valid_to' => $validTo,
    ])))->toThrow(QueryException::class);
})->with(['2020-01-01', '2019-12-31']);

it('impide intervalos históricos superpuestos aunque correspondan a regiones distintas', function (string $from, ?string $to): void {
    $membership = MunicipalityRegionMembership::factory()->create([
        'valid_from' => '2020-01-01',
        'valid_to' => '2020-07-01',
    ]);
    $newRegion = Region::factory()->create();

    expect(fn () => DB::transaction(fn () => MunicipalityRegionMembership::factory()->agreement()->create([
        'municipality_id' => $membership->municipality_id,
        'region_id' => $newRegion->id,
        'valid_from' => $from,
        'valid_to' => $to,
    ])))->toThrow(QueryException::class);
})->with([
    ['2020-01-01', '2020-07-01'],
    ['2020-02-01', '2020-03-01'],
    ['2019-01-01', '2021-01-01'],
    ['2019-12-01', '2020-01-02'],
    ['2020-06-30', '2020-08-01'],
    ['2020-06-01', null],
    ['2019-01-01', null],
]);

it('impide dos periodos abiertos simultáneos para un municipio', function (): void {
    $membership = MunicipalityRegionMembership::factory()->create(['valid_from' => '2020-01-01']);

    expect(fn () => DB::transaction(fn () => MunicipalityRegionMembership::factory()->agreement()->create([
        'municipality_id' => $membership->municipality_id,
        'valid_from' => '2021-01-01',
    ])))->toThrow(QueryException::class);

    expect($membership->municipality->regionMemberships()->whereNull('valid_to')->count())->toBe(1);
});

it('acepta intervalos contiguos y pertenencias simultáneas de municipios diferentes', function (): void {
    $first = MunicipalityRegionMembership::factory()->create([
        'valid_from' => '2020-01-01',
        'valid_to' => '2021-01-01',
    ]);
    $second = MunicipalityRegionMembership::factory()->agreement()->create([
        'municipality_id' => $first->municipality_id,
        'valid_from' => '2021-01-01',
    ]);
    $otherMunicipality = MunicipalityRegionMembership::factory()->create(['valid_from' => '2020-01-01']);

    $this->assertModelExists($second);
    $this->assertModelExists($otherMunicipality);
    expect($first->municipality->regionMemberships()->count())->toBe(2);
});

it('impide crear un segundo punto de partida incluso sin solapamientos', function (): void {
    $membership = MunicipalityRegionMembership::factory()->create([
        'valid_from' => '2020-01-01',
        'valid_to' => '2021-01-01',
    ]);

    expect(fn () => DB::transaction(fn () => MunicipalityRegionMembership::factory()->create([
        'municipality_id' => $membership->municipality_id,
        'valid_from' => '2021-01-01',
    ])))->toThrow(QueryException::class);
});

it('exige una procedencia válida y referencias no vacías para los acuerdos también en inserciones directas', function (string $origin, ?string $reference): void {
    $attributes = MunicipalityRegionMembership::factory()->raw([
        'origin_type' => $origin,
        'agreement_reference' => $reference,
    ]);

    expect(fn () => DB::transaction(fn () => DB::table((new MunicipalityRegionMembership)->getTable())->insert($attributes)))
        ->toThrow(QueryException::class);
})->with([
    ['unknown', null],
    ['correction', 'RES-001'],
    ['agreement', null],
    ['agreement', ''],
    ['agreement', '   '],
]);

it('revierte y recrea únicamente la tabla de historial sin eliminar btree_gist ni los municipios', function (): void {
    $municipality = Municipality::factory()->create();
    $migration = require database_path('migrations/2026_11_03_133013_create_municipality_region_memberships_table.php');

    $migration->down();

    expect(Schema::hasTable('municipality_region_memberships'))->toBeFalse();
    $this->assertModelExists($municipality);

    $migration->up();

    expect(Schema::hasTable('municipality_region_memberships'))->toBeTrue()
        ->and(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'btree_gist') AS installed")->installed)->toBeTrue();

    MunicipalityRegionMembership::factory()->for($municipality)->create();

    expect(fn () => DB::transaction(fn () => MunicipalityRegionMembership::factory()->agreement()->for($municipality)->create()))
        ->toThrow(QueryException::class);
    $this->assertModelExists($municipality);
});
