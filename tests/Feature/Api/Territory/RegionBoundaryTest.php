<?php

use App\Models\Territory\Region;
use App\Models\Territory\RegionBoundary;
use App\Models\User;
use App\Services\Territory\RegionBoundaryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mapUser = User::factory()->create();
    $this->mapUser->givePermissionTo('regions.view');
});

it('protege la geometría regional con autenticación y permisos', function () {
    $boundary = RegionBoundary::factory()->create();
    $path = '/api/admin/regions/'.$boundary->region_id.'/boundary';

    $this->getJson($path)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->getJson($path)->assertForbidden();
});

it('entrega un GeoJSON estándar sin exponer atributos originales', function () {
    $boundary = RegionBoundary::factory()->create();

    $this->actingAs($this->mapUser, 'api')
        ->getJson('/api/admin/regions/'.$boundary->region_id.'/boundary')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/geo+json')
        ->assertJsonPath('type', 'Feature')
        ->assertJsonPath('geometry.type', 'MultiPolygon')
        ->assertJsonPath('properties.region_id', $boundary->region_id)
        ->assertJsonMissingPath('data')
        ->assertJsonMissingPath('source_properties');

    $boundary->region->delete();

    $this->getJson('/api/admin/regions/'.$boundary->region_id.'/boundary')->assertNotFound();
});

it('devuelve 404 cuando la región aún no tiene límite', function () {
    $region = Region::factory()->create();

    $this->actingAs($this->mapUser, 'api')
        ->getJson('/api/admin/regions/'.$region->id.'/boundary')
        ->assertNotFound();
});

it('importa polígonos y exige una sustitución explícita', function () {
    $region = Region::factory()->create();
    $collection = [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'properties' => ['MUNICIPIO' => 'Viacha'],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[-68.2, -16.6], [-68.1, -16.6], [-68.1, -16.5], [-68.2, -16.6]]],
            ],
        ]],
    ];
    $service = app(RegionBoundaryService::class);
    $boundary = $service->import($region, $collection, 'reg_metro.shp', 32719);

    expect($service->find($region)->geometry['type'])->toBe('MultiPolygon')
        ->and($boundary->source_properties[0]['MUNICIPIO'])->toBe('Viacha');

    expect(fn () => $service->import($region, $collection, 'reg_metro.shp', 32719))
        ->toThrow(ValidationException::class);

    expect($service->import($region, $collection, 'replacement.shp', 32719, true)->id)->toBe($boundary->id);
    $this->assertDatabaseCount('region_boundaries', 1);
});

it('rechaza capas vacías o que no son polígonos', function (array $features) {
    $region = Region::factory()->create();

    expect(fn () => app(RegionBoundaryService::class)->import(
        $region, ['type' => 'FeatureCollection', 'features' => $features], 'invalid.shp', 4326,
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('region_boundaries', 0);
})->with([
    'vacío' => [[]],
    'punto' => [[['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [-68, -16]]]]],
]);

it('rechaza un polígono que se cruza a sí mismo', function () {
    $region = Region::factory()->create();

    expect(fn () => app(RegionBoundaryService::class)->import($region, [
        'type' => 'FeatureCollection',
        'features' => [[
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[-68.2, -16.6], [-68.1, -16.5], [-68.1, -16.6], [-68.2, -16.5], [-68.2, -16.6]]],
            ],
        ]],
    ], 'invalid.shp', 4326))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('region_boundaries', 0);
});
