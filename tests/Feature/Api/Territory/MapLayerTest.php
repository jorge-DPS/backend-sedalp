<?php

use App\Models\Territory\Region;
use App\Models\Territory\RegionBoundary;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['maps.view', 'maps.create', 'maps.update', 'regions.update', 'regions.view']);
});

function layerUpload(string $type = 'Point', ?array $coordinates = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent('layer.geojson', json_encode([
        'type' => 'Feature', 'properties' => ['name' => 'Estación <script>'],
        'geometry' => ['type' => $type, 'coordinates' => $coordinates ?? [-68.15, -16.5]],
    ], JSON_THROW_ON_ERROR));
}

it('protege las capas y su carga con autenticación y permisos', function () {
    $this->getJson('/api/admin/map-layers')->assertUnauthorized();
    $this->postJson('/api/admin/map-layers/preview', ['file' => layerUpload()])->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api');
    $this->getJson('/api/admin/map-layers')->assertForbidden();
    $this->postJson('/api/admin/map-layers', ['file' => layerUpload()])->assertForbidden();
    $this->postJson('/api/admin/map-layers/preview', ['file' => layerUpload()])->assertForbidden();
});

it('previsualiza sin guardar y conserva puntos líneas y polígonos', function (string $type, array $coordinates) {
    $this->actingAs($this->editor, 'api');
    $payload = ['name' => 'Capa de prueba', 'kind' => 'overlay', 'file' => layerUpload($type, $coordinates)];
    $this->postJson('/api/admin/map-layers/preview', $payload)->assertOk()
        ->assertJsonPath('data.summary.feature_count', 1)->assertJsonPath('data.features.0.geometry.type', $type);
    $this->assertDatabaseCount('map_layers', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $created = $this->postJson('/api/admin/map-layers', $payload)->assertCreated()
        ->assertJsonMissingPath('data.current_version.source_path');
    $id = $created->json('data.id');
    $this->getJson("/api/admin/map-layers/$id/geojson")->assertOk()
        ->assertHeader('Content-Type', 'application/geo+json')
        ->assertJsonPath('features.0.geometry.type', $type)
        ->assertJsonPath('features.0.properties.name', 'Estación <script>');
    $this->getJson('/api/admin/map-layers?search=prueba')->assertOk()->assertJsonCount(1, 'data');
    expect(Storage::disk('local')->allFiles('maps/originals'))->toHaveCount(1);
})->with([
    'punto' => ['Point', [-68.15, -16.5]],
    'línea' => ['LineString', [[-68, -16], [-68.1, -16.1]]],
    'polígono' => ['Polygon', [[[-68, -16], [-68.1, -16], [-68.1, -16.1], [-68, -16]]]],
    'multipunto' => ['MultiPoint', [[-68, -16], [-68.1, -16.1]]],
    'multilínea' => ['MultiLineString', [[[-68, -16], [-68.1, -16.1]]]],
    'multipolígono' => ['MultiPolygon', [[[[-68, -16], [-68.1, -16], [-68.1, -16.1], [-68, -16]]]]],
]);

it('versiona sin perder datos y rechaza actualizaciones desfasadas', function () {
    $this->actingAs($this->editor, 'api');
    $created = $this->postJson('/api/admin/map-layers', ['name' => 'Estaciones', 'kind' => 'overlay', 'file' => layerUpload()])->assertCreated();
    $id = $created->json('data.id');
    $payload = ['file' => layerUpload('Point', [-67, -15]), 'expected_version_id' => $created->json('data.current_version.id')];
    $this->postJson("/api/admin/map-layers/$id/versions", $payload)->assertCreated()->assertJsonPath('data.current_version.number', 2);
    $this->postJson("/api/admin/map-layers/$id/versions", $payload)->assertConflict();
    $this->getJson("/api/admin/map-layers/$id/versions")->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/admin/map-layers/$id/geojson")->assertOk()->assertJsonPath('features.0.geometry.coordinates.0', -67);
    $this->assertDatabaseCount('map_features', 2);
    expect(Storage::disk('local')->allFiles('maps/originals'))->toHaveCount(2);
});

it('exige confirmación para sustituir el límite y sincroniza la API regional', function () {
    $boundary = RegionBoundary::factory()->create();
    $this->actingAs($this->editor, 'api');
    $payload = ['name' => 'Límite regional', 'kind' => 'boundary', 'region_id' => $boundary->region_id,
        'file' => layerUpload('Polygon', [[[-68, -16], [-68.1, -16], [-68.1, -16.1], [-68, -16]]])];
    $this->postJson('/api/admin/map-layers', $payload)->assertUnprocessable()->assertJsonValidationErrors('replace_boundary');
    $this->assertDatabaseCount('map_layers', 0);
    expect(Storage::disk('local')->allFiles('maps/originals'))->toBe([]);
    $this->postJson('/api/admin/map-layers', $payload + ['replace_boundary' => true])->assertCreated();
    $this->getJson('/api/admin/regions/'.$boundary->region_id.'/boundary')->assertOk()->assertJsonPath('geometry.type', 'MultiPolygon');
    $this->assertDatabaseCount('region_boundaries', 1);
    $this->postJson('/api/admin/map-layers', $payload + ['replace_boundary' => true])->assertUnprocessable()->assertJsonValidationErrors('region_id');
});

it('no permite límites de puntos ni regiones eliminadas ni permisos insuficientes', function () {
    $boundary = RegionBoundary::factory()->create();
    $this->actingAs($this->editor, 'api');
    $payload = ['name' => 'Límite', 'kind' => 'boundary', 'region_id' => $boundary->region_id, 'replace_boundary' => true, 'file' => layerUpload()];
    $this->postJson('/api/admin/map-layers', $payload)->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->editor->revokePermissionTo('regions.update');
    $this->postJson('/api/admin/map-layers', $payload)->assertForbidden();
    $this->assertDatabaseCount('map_layers', 0);
    expect(Storage::disk('local')->allFiles('maps/originals'))->toBe([]);
});

it('rechaza GeoJSON inválido sin persistir archivos', function (array $geometry) {
    $this->actingAs($this->editor, 'api');
    $file = UploadedFile::fake()->createWithContent('invalid.geojson', json_encode(['type' => 'Feature', 'geometry' => $geometry]));
    $this->postJson('/api/admin/map-layers', ['name' => 'Inválido', 'kind' => 'overlay', 'file' => $file])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('map_layers', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    [['type' => 'Point', 'coordinates' => [600000, 8000000]]],
    [['type' => 'LineString', 'coordinates' => []]],
    [['type' => 'Polygon', 'coordinates' => [[[-68, -16], [-67, -15], [-68, -15], [-67, -16], [-68, -16]]]]],
    [['type' => 'GeometryCollection', 'geometries' => []]],
]);

it('limita extensiones tamaño y cantidad de elementos', function () {
    $this->actingAs($this->editor, 'api');
    $this->postJson('/api/admin/map-layers/preview', ['file' => UploadedFile::fake()->create('data.exe', 1)])->assertUnprocessable();
    $this->postJson('/api/admin/map-layers/preview', ['file' => UploadedFile::fake()->create('data.geojson', 10241)])->assertUnprocessable();
    config(['maps.max_features' => 0]);
    $this->postJson('/api/admin/map-layers/preview', ['file' => layerUpload()])->assertUnprocessable();
});

it('repara solo bajo solicitud explícita y guarda el resultado validado', function () {
    $this->actingAs($this->editor, 'api');
    $payload = ['name' => 'Reparación', 'kind' => 'overlay', 'repair_geometries' => true,
        'file' => layerUpload('Polygon', [[[-68, -16], [-67, -15], [-68, -15], [-67, -16], [-68, -16]]])];
    $this->postJson('/api/admin/map-layers/preview', $payload)->assertOk()->assertJsonPath('data.summary.repaired_features', 1);
    $this->postJson('/api/admin/map-layers', $payload)->assertCreated()->assertJsonPath('data.current_version.summary.repaired_features', 1);
});

it('rechaza estructuras mal formadas con errores de validación', function (string $json) {
    $this->actingAs($this->editor, 'api');
    $this->postJson('/api/admin/map-layers/preview', [
        'file' => UploadedFile::fake()->createWithContent('invalid.json', $json),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->assertDatabaseCount('map_layers', 0);
})->with([
    'no json' => ['not-json'],
    'feature escalar' => ['{"type":"FeatureCollection","features":[1]}'],
    'punto mal formado' => ['{"type":"Feature","geometry":{"type":"Point","coordinates":[[-68,-16]]}}'],
    'anillo sin cerrar' => ['{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-68,-16],[-67,-16],[-67,-15]]]}}'],
    'proyección incorrecta' => ['{"type":"Feature","crs":{"properties":{"name":"EPSG:32719"}},"geometry":{"type":"Point","coordinates":[-68,-16]}}'],
]);

it('previsualiza y guarda el ZIP real de Altiplano Norte por la API', function () {
    $directory = env('MAPS_TEST_REGIONS_DIRECTORY');
    $path = $directory.'/reg_Altiplano Norte/reg_Altiplano Norte.zip';
    if (! $directory || ! is_file($path)) {
        $this->markTestSkipped('ZIP regional local opcional no disponible.');
    }
    $this->actingAs($this->editor, 'api')->postJson('/api/admin/map-layers/preview', [
        'file' => new UploadedFile($path, basename($path), 'application/zip', null, true),
        'repair_geometries' => true,
    ])->assertOk()->assertJsonPath('data.summary.feature_count', 1);
    $region = Region::factory()->create();
    $this->postJson('/api/admin/map-layers', [
        'name' => 'Altiplano Norte',
        'kind' => 'boundary',
        'region_id' => $region->id,
        'file' => new UploadedFile($path, basename($path), 'application/zip', null, true),
    ])->assertCreated()->assertJsonPath('data.current_version.summary.repaired_features', 0);
    $this->getJson('/api/admin/regions/'.$region->id.'/boundary')
        ->assertOk()->assertJsonPath('geometry.type', 'MultiPolygon');
    $this->getJson('/api/admin/region-boundaries')
        ->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.region_id', $region->id)
        ->assertJsonPath('features.0.geometry.type', 'MultiPolygon');
});
