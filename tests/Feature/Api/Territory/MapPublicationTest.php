<?php

use App\Models\Territory\MapFeature;
use App\Models\Territory\MapLayer;
use App\Models\Territory\MapLayerVersion;
use App\Models\Territory\RegionBoundary;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->publisher = User::factory()->create();
    $this->publisher->givePermissionTo(['maps.view', 'maps.create', 'maps.update', 'maps.publish', 'regions.update']);
    $this->feature = MapFeature::factory()->create(['properties' => ['secret' => 'private-attribute']]);
    $this->version = MapLayerVersion::findOrFail($this->feature->map_layer_version_id);
    $this->layer = $this->version->mapLayer;
    $this->publicationUrl = '/api/admin/map-layers/'.$this->layer->id.'/publication';
    $this->publicUrl = '/api/public/map-layers/'.$this->layer->id;
    $this->publishPayload = ['expected_version_id' => $this->version->id, 'expected_published_version_id' => null, 'category' => 'roads'];
});

it('oculta borradores en todas las rutas públicas y protege la publicación', function () {
    $this->getJson('/api/public/map-layers')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson($this->publicUrl)->assertNotFound();
    $this->getJson($this->publicUrl.'/geojson')->assertNotFound();
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api');
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertForbidden();
    $this->deleteJson($this->publicationUrl, $this->publishPayload)->assertForbidden();
    $this->publisher->revokePermissionTo('maps.publish');
    $this->actingAs($this->publisher, 'api');
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertForbidden();
});

it('publica solo la versión revisada y no expone atributos ni archivos privados', function () {
    $this->actingAs($this->publisher, 'api');
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertOk()
        ->assertJsonPath('data.publication_status', 'published')
        ->assertJsonPath('data.published_version.id', $this->version->id);
    $this->getJson($this->publicUrl)->assertOk()->assertJsonPath('data.category', 'roads')
        ->assertJsonMissingPath('data.current_version')->assertJsonMissingPath('data.source_filename')
        ->assertJsonMissingPath('data.published_by')->assertJsonPath('data.version', 1);
    $this->getJson($this->publicUrl.'/geojson')->assertOk()
        ->assertJsonPath('features.0.properties.layer_id', $this->layer->id)
        ->assertJsonMissingPath('features.0.properties.secret')
        ->assertJsonCount(4, 'features.0.properties')->assertHeader('Content-Type', 'application/geo+json');
    $this->getJson('/api/public/map-layers?category=roads')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/public/map-layers?category=rivers')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/public/map-layers?per_page=500')->assertUnprocessable();
});

it('conserva la geometría publicada al cargar otro archivo y permite publicar y retirar después', function () {
    $this->actingAs($this->publisher, 'api');
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertOk();
    $file = UploadedFile::fake()->createWithContent('new.geojson', json_encode([
        'type' => 'Feature', 'properties' => ['internal' => 'private'],
        'geometry' => ['type' => 'Point', 'coordinates' => [-67, -15]],
    ]));
    $new = $this->postJson('/api/admin/map-layers/'.$this->layer->id.'/versions', [
        'file' => $file, 'expected_version_id' => $this->version->id,
    ])->assertCreated()->assertJsonPath('data.publication_status', 'pending')
        ->assertJsonPath('data.published_version.id', $this->version->id)->json('data.current_version.id');
    $this->getJson($this->publicUrl.'/geojson')->assertOk()->assertJsonPath('features.0.geometry.coordinates.0', -68.15);
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertConflict();
    $payload = ['expected_version_id' => $new, 'expected_published_version_id' => $this->version->id];
    $this->putJson($this->publicationUrl, $payload + ['category' => 'roads'])->assertOk();
    $this->getJson($this->publicUrl.'/geojson')->assertOk()->assertJsonPath('features.0.geometry.coordinates.0', -67);
    $this->deleteJson($this->publicationUrl, $payload)->assertConflict();
    $this->deleteJson($this->publicationUrl, ['expected_version_id' => $new, 'expected_published_version_id' => $new])
        ->assertOk()->assertJsonPath('data.publication_status', 'draft');
    $this->getJson($this->publicUrl)->assertNotFound();
    $this->getJson($this->publicUrl.'/geojson')->assertNotFound();
    $this->assertDatabaseCount('map_layer_versions', 2);
    $this->assertDatabaseCount('map_features', 2);
});

it('rechaza categorías incompatibles, versiones ajenas y formularios incompletos', function () {
    $this->actingAs($this->publisher, 'api');
    $this->putJson($this->publicationUrl, array_replace($this->publishPayload, ['category' => 'boundaries']))
        ->assertUnprocessable()->assertJsonValidationErrors('category');
    $other = MapLayerVersion::factory()->create();
    $this->putJson($this->publicationUrl, array_replace($this->publishPayload, ['expected_version_id' => $other->id]))->assertConflict();
    $this->putJson($this->publicationUrl, ['expected_version_id' => $this->version->id, 'category' => 'roads'])
        ->assertUnprocessable()->assertJsonValidationErrors('expected_published_version_id');
    $this->getJson('/api/public/map-layers?category=invalid')->assertUnprocessable();
});

it('incorpora límites antiguos una sola vez sin alterar el original ni publicar', function () {
    $boundary = RegionBoundary::factory()->create(['source_properties' => ['private' => 'original']]);
    $before = DB::table('region_boundaries')->where('id', $boundary->id)->first();
    $this->artisan('maps:import-legacy', ['--user' => $this->publisher->id])->assertSuccessful();
    $layer = MapLayer::where('region_id', $boundary->region_id)->where('kind', 'boundary')->firstOrFail();
    expect($layer->published_version_id)->toBeNull();
    expect($layer->currentVersion->features()->count())->toBe(1);
    expect(Storage::disk('local')->exists($layer->currentVersion->source_path))->toBeTrue();
    $this->artisan('maps:import-legacy', ['--user' => $this->publisher->id])->assertSuccessful();
    expect(MapLayer::where('region_id', $boundary->region_id)->count())->toBe(1);
    expect(DB::table('region_boundaries')->where('id', $boundary->id)->first())->toEqual($before);
    $this->getJson('/api/public/region-boundaries')->assertOk()->assertJsonCount(0, 'features');
    $this->actingAs($this->publisher, 'api');
    $this->putJson('/api/admin/map-layers/'.$layer->id.'/publication', [
        'expected_version_id' => $layer->currentVersion->id, 'expected_published_version_id' => null, 'category' => 'boundaries',
    ])->assertOk();
    $this->getJson('/api/public/region-boundaries')->assertOk()->assertJsonCount(1, 'features')
        ->assertJsonPath('features.0.properties.region_id', $boundary->region_id)->assertJsonMissingPath('features.0.properties.private');
    $boundary->region->delete();
    $this->getJson('/api/public/region-boundaries')->assertOk()->assertJsonCount(0, 'features');
    $this->getJson('/api/public/map-layers/'.$layer->id.'/geojson')->assertNotFound();
});

it('no publica capas sin geometría y no permite importar con un usuario sin permisos', function () {
    $this->feature->delete();
    $this->actingAs($this->publisher, 'api');
    $this->putJson($this->publicationUrl, $this->publishPayload)->assertUnprocessable();
    $user = User::factory()->create();
    $this->artisan('maps:import-legacy', ['--user' => $user->id])->assertFailed();
});
