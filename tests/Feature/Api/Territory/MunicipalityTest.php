<?php

use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Models\User;
use App\Services\Territory\MunicipalityRegionMembershipService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->territoryUser = User::factory()->create();
    $this->territoryUser->givePermissionTo([
        'municipalities.view',
        'municipalities.create',
        'municipalities.update',
        'municipalities.delete',
    ]);
    $this->region = Region::factory()->create();
    $this->province = Province::factory()->create();
    $this->municipalityPayload = [
        'region_id' => $this->region->id,
        'province_id' => $this->province->id,
        'name' => 'Municipio de prueba',
    ];
});

it('protege todas las operaciones de municipios', function (string $method, bool $member) {
    $municipality = Municipality::factory()->create();
    $url = '/api/admin/municipalities'.($member ? '/'.$municipality->id : '');

    $this->json($method, $url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->json($method, $url)->assertForbidden();
})->with([
    'index' => ['GET', false],
    'show' => ['GET', true],
    'store' => ['POST', false],
    'patch' => ['PATCH', true],
    'put' => ['PUT', true],
    'delete' => ['DELETE', true],
]);

it('crea municipios con relaciones y conserva ceros iniciales del código oficial', function () {
    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/municipalities', [
            ...$this->municipalityPayload,
            'name' => '  Municipio de prueba  ',
            'official_code' => '  00123  ',
            'id' => 999999,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Municipio de prueba')
        ->assertJsonPath('data.official_code', '00123')
        ->assertJsonPath('data.region.id', $this->region->id)
        ->assertJsonPath('data.province.id', $this->province->id)
        ->assertJsonPath('data.active', true);

    $this->assertDatabaseHas('municipalities', [
        ...$this->municipalityPayload,
        'official_code' => '00123',
    ]);
    $this->assertDatabaseMissing('municipalities', ['id' => 999999]);
});

it('permite varios municipios sin código oficial', function () {
    $this->actingAs($this->territoryUser, 'api');
    foreach (['Primero', 'Segundo'] as $name) {
        $this->postJson('/api/admin/municipalities', [...$this->municipalityPayload, 'name' => $name])
            ->assertCreated()->assertJsonPath('data.official_code', null);
    }
});

it('rechaza municipios con datos o relaciones inválidas', function (array $overrides, string $field) {
    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/municipalities', [...$this->municipalityPayload, ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing region' => [['region_id' => null], 'region_id'],
    'unknown region' => [['region_id' => 999999], 'region_id'],
    'array region' => [['region_id' => [1]], 'region_id'],
    'missing province' => [['province_id' => null], 'province_id'],
    'unknown province' => [['province_id' => 999999], 'province_id'],
    'array province' => [['province_id' => [1]], 'province_id'],
    'empty name' => [['name' => '   '], 'name'],
    'array name' => [['name' => ['incorrecto']], 'name'],
    'long name' => [['name' => str_repeat('a', 151)], 'name'],
    'numeric official code' => [['official_code' => 123], 'official_code'],
    'long official code' => [['official_code' => str_repeat('a', 21)], 'official_code'],
]);

it('rechaza asignar un municipio a un catálogo eliminado', function (string $relation) {
    $catalog = $this->{$relation};
    $catalog->delete();

    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/municipalities', $this->municipalityPayload)
        ->assertUnprocessable()->assertJsonValidationErrors($relation.'_id');
})->with(['region', 'province']);

it('filtra por región y provincia sin mezclar municipios con nombres coincidentes', function () {
    $match = Municipality::factory()->for($this->region)->for($this->province)->inactive()
        ->create(['name' => 'Coincidente', 'official_code' => '00123']);
    Municipality::factory()->for($this->region)->create(['name' => 'Coincidente']);
    Municipality::factory()->for($this->province)->create(['name' => 'Fuera']);
    Municipality::factory()->for($this->region)->for($this->province)->create()->delete();

    $this->actingAs($this->territoryUser, 'api')
        ->getJson("/api/admin/municipalities?region_id={$this->region->id}&province_id={$this->province->id}&active=false&search=%2000123%20&per_page=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.region.id', $this->region->id)
        ->assertJsonPath('data.0.province.id', $this->province->id);
});

it('rechaza filtros inválidos de municipios', function (string $query, string $field) {
    $this->actingAs($this->territoryUser, 'api')
        ->getJson('/api/admin/municipalities?'.$query)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['region_id=999999', 'region_id'],
    ['province_id[]=1', 'province_id'],
    ['active=invalid', 'active'],
    ['per_page=101', 'per_page'],
    ['page=0', 'page'],
]);

it('permite cambiar de región manteniendo la provincia y limpiar el código oficial', function () {
    $municipality = Municipality::factory()->for($this->region)->for($this->province)->create(['official_code' => '00123']);
    $newRegion = Region::factory()->create();
    app(MunicipalityRegionMembershipService::class)->initialize($municipality, today()->subYear()->toDateString());

    $this->actingAs($this->territoryUser, 'api')
        ->patchJson("/api/admin/municipalities/{$municipality->id}", [
            'region_id' => $newRegion->id,
            'official_code' => null,
            'valid_from' => today()->toDateString(),
            'agreement_reference' => 'RES-TEST-001',
        ])
        ->assertOk()
        ->assertJsonPath('data.region.id', $newRegion->id)
        ->assertJsonPath('data.province.id', $this->province->id)
        ->assertJsonPath('data.official_code', null);

    expect($newRegion->municipalities()->whereKey($municipality->id)->exists())->toBeTrue();
    expect($this->region->municipalities()->whereKey($municipality->id)->exists())->toBeFalse();
    expect($municipality->regionMemberships()->count())->toBe(2)
        ->and($municipality->regionMemberships()->whereNull('valid_to')->sole()->region_id)->toBe($newRegion->id);
});

it('impide duplicar nombres dentro de una provincia incluso en cambios parciales', function () {
    Municipality::factory()->for($this->province)->create(['name' => 'Repetido']);
    $other = Municipality::factory()->create(['name' => 'Repetido']);

    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/municipalities', [...$this->municipalityPayload, 'name' => 'Repetido'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->patchJson("/api/admin/municipalities/{$other->id}", ['province_id' => $this->province->id])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->patchJson("/api/admin/municipalities/{$other->id}", ['name' => 'Repetido'])
        ->assertOk();
});

it('reserva el código oficial tras el borrado lógico', function () {
    Municipality::factory()->create(['official_code' => '00123'])->delete();
    $other = Municipality::factory()->create();

    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/municipalities', [...$this->municipalityPayload, 'official_code' => '00123'])
        ->assertUnprocessable()->assertJsonValidationErrors('official_code');
    $this->patchJson("/api/admin/municipalities/{$other->id}", ['official_code' => '00123'])
        ->assertUnprocessable()->assertJsonValidationErrors('official_code');
});

it('muestra y elimina un municipio sin borrar sus catálogos', function () {
    $municipality = Municipality::factory()->for($this->region)->for($this->province)->create();

    $this->actingAs($this->territoryUser, 'api')
        ->getJson("/api/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertJsonPath('data.region.id', $this->region->id)
        ->assertJsonPath('data.province.id', $this->province->id);

    $this->deleteJson("/api/admin/municipalities/{$municipality->id}")->assertNoContent();
    $this->assertSoftDeleted('municipalities', ['id' => $municipality->id]);
    $this->getJson("/api/admin/municipalities/{$municipality->id}")->assertNotFound();
    expect($this->region->fresh()->trashed())->toBeFalse();
    expect($this->province->fresh()->trashed())->toBeFalse();
});

it('mantiene constante la cantidad de consultas de relaciones al listar municipios', function () {
    Municipality::factory()->for($this->region)->for($this->province)->create();
    $this->actingAs($this->territoryUser, 'api')->getJson('/api/admin/municipalities')->assertOk();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_starts_with($query->sql, 'select') && (str_contains($query->sql, 'from "regions"') || str_contains($query->sql, 'from "provinces"'))) {
            $queries[] = $query->sql;
        }
    });

    $this->getJson('/api/admin/municipalities')->assertOk();
    $singleCount = count($queries);

    Municipality::factory()->count(10)->for($this->region)->for($this->province)->create();
    $queries = [];

    $this->getJson('/api/admin/municipalities')->assertOk()->assertJsonCount(11, 'data');
    expect($singleCount)->toBe(2)->and(count($queries))->toBe($singleCount);
});

it('rechaza por la API los cambios regionales sin fecha efectiva o acuerdo', function (array $agreement, string $field): void {
    $municipality = Municipality::factory()->for($this->region)->create();
    $newRegion = Region::factory()->create();
    app(MunicipalityRegionMembershipService::class)->initialize($municipality, today()->subYear()->toDateString());

    $this->actingAs($this->territoryUser, 'api')
        ->patchJson("/api/admin/municipalities/{$municipality->id}", [
            'region_id' => $newRegion->id,
            ...$agreement,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($municipality->fresh()->region_id)->toBe($this->region->id)
        ->and($municipality->regionMemberships()->sole()->valid_to)->toBeNull();
})->with([
    'sin metadatos' => [[], 'valid_from'],
    'sin acuerdo' => [['valid_from' => today()->toDateString()], 'agreement_reference'],
    'sin fecha efectiva' => [['agreement_reference' => 'RES-TEST-001'], 'valid_from'],
    'acuerdo vacío' => [['valid_from' => today()->toDateString(), 'agreement_reference' => '   '], 'agreement_reference'],
    'fecha futura' => [['valid_from' => today()->addDay()->toDateString(), 'agreement_reference' => 'RES-TEST-001'], 'valid_from'],
]);

it('rechaza por la API cambios hacia regiones inactivas y cambios simultáneos de provincia', function (bool $changeProvince): void {
    $municipality = Municipality::factory()->for($this->region)->for($this->province)->create();
    $newRegion = Region::factory()->create(['active' => $changeProvince]);
    app(MunicipalityRegionMembershipService::class)->initialize($municipality, today()->subYear()->toDateString());
    $data = [
        'region_id' => $newRegion->id,
        'valid_from' => today()->toDateString(),
        'agreement_reference' => 'RES-TEST-001',
    ];

    if ($changeProvince) {
        $data['province_id'] = Province::factory()->create()->id;
    }

    $this->actingAs($this->territoryUser, 'api')
        ->patchJson("/api/admin/municipalities/{$municipality->id}", $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($changeProvince ? 'province_id' : 'region_id');

    expect($municipality->fresh()->province_id)->toBe($this->province->id)
        ->and($municipality->fresh()->region_id)->toBe($this->region->id)
        ->and($municipality->regionMemberships()->count())->toBe(1);
})->with([false, true]);

it('conserva el contrato de edición de atributos sin exigir acuerdos ni crear historial adicional', function (): void {
    $municipality = Municipality::factory()->for($this->region)->create();
    app(MunicipalityRegionMembershipService::class)->initialize($municipality, today()->subYear()->toDateString());
    $history = $municipality->regionMemberships()->get()->toArray();

    $this->actingAs($this->territoryUser, 'api')
        ->patchJson("/api/admin/municipalities/{$municipality->id}", [
            'region_id' => $this->region->id,
            'name' => 'Nombre actualizado',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nombre actualizado');

    expect($municipality->regionMemberships()->get()->toArray())->toBe($history);
});
