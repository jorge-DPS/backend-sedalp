<?php

use App\Enums\Auth\PermissionName;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->territoryUser = User::factory()->create();
    $this->territoryUser->givePermissionTo(
        collect(PermissionName::cases())
            ->map(fn (PermissionName $permission): string => $permission->value)
            ->filter(fn (string $permission): bool => str_starts_with($permission, 'regions.') || str_starts_with($permission, 'provinces.'))
            ->all()
    );
});

dataset('territorial catalogs', [
    'regions' => [Region::class, 'regions', 'region'],
    'provinces' => [Province::class, 'provinces', 'province'],
]);

it('protege todas las operaciones de los catálogos territoriales', function (string $model, string $table, string $relation, string $method, bool $member) {
    $record = $model::factory()->create();
    $url = '/api/admin/'.$table.($member ? '/'.$record->id : '');

    $this->json($method, $url)->assertUnauthorized();

    $this->actingAs(User::factory()->create(), 'api')
        ->json($method, $url)
        ->assertForbidden();
})->with('territorial catalogs')->with([
    'index' => ['GET', false],
    'show' => ['GET', true],
    'store' => ['POST', false],
    'patch' => ['PATCH', true],
    'put' => ['PUT', true],
    'delete' => ['DELETE', true],
]);

it('gestiona un catálogo territorial y conserva códigos opcionales', function (string $model, string $table) {
    $this->actingAs($this->territoryUser, 'api');

    $id = $this->postJson('/api/admin/'.$table, ['name' => '  Catálogo de prueba  '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Catálogo de prueba')
        ->assertJsonPath('data.code', null)
        ->assertJsonPath('data.active', true)
        ->json('data.id');

    $this->getJson("/api/admin/{$table}/{$id}")
        ->assertOk()
        ->assertJsonPath('data.municipalities_count', 0);

    $this->patchJson("/api/admin/{$table}/{$id}", ['code' => '  cat-01  ', 'active' => false])
        ->assertOk()
        ->assertJsonPath('data.code', 'CAT-01')
        ->assertJsonPath('data.active', false);

    $this->patchJson("/api/admin/{$table}/{$id}", ['name' => 'Catálogo de prueba', 'code' => 'CAT-01'])
        ->assertOk();

    $this->patchJson("/api/admin/{$table}/{$id}", ['code' => null])
        ->assertOk()
        ->assertJsonPath('data.code', null);

    $this->deleteJson("/api/admin/{$table}/{$id}")->assertNoContent();
    $this->assertSoftDeleted($table, ['id' => $id]);
    $this->getJson("/api/admin/{$table}/{$id}")->assertNotFound();
    $this->getJson('/api/admin/'.$table)->assertOk()->assertJsonCount(0, 'data');
})->with('territorial catalogs');

it('filtra y pagina los catálogos con sus cantidades de municipios', function (string $model, string $table, string $relation) {
    $match = $model::factory()->inactive()->create(['name' => 'Catálogo objetivo', 'code' => 'CAT-01']);
    $model::factory()->create(['name' => 'Otro catálogo']);
    Municipality::factory()->count(2)->for($match, $relation)->create();
    Municipality::factory()->for($match, $relation)->create()->delete();

    $this->actingAs($this->territoryUser, 'api')
        ->getJson("/api/admin/{$table}?search=%20cat-01%20&active=false&per_page=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.municipalities_count', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 1);
})->with('territorial catalogs');

it('rechaza entradas inválidas sin convertirlas a cadenas', function (string $model, string $table, string $relation, array $payload, string $field) {
    $this->actingAs($this->territoryUser, 'api')
        ->postJson('/api/admin/'.$table, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with('territorial catalogs')->with([
    'empty name' => [['name' => '   '], 'name'],
    'array name' => [['name' => ['incorrecto']], 'name'],
    'array code' => [['name' => 'Catálogo', 'code' => ['incorrecto']], 'code'],
    'numeric code' => [['name' => 'Catálogo', 'code' => 123], 'code'],
    'invalid active' => [['name' => 'Catálogo', 'active' => 'invalid'], 'active'],
    'long code' => [['name' => 'Catálogo', 'code' => str_repeat('a', 51)], 'code'],
]);

it('rechaza filtros inválidos de los catálogos territoriales', function (string $model, string $table, string $relation, string $query, string $field) {
    $this->actingAs($this->territoryUser, 'api')
        ->getJson("/api/admin/{$table}?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with('territorial catalogs')->with([
    ['active=invalid', 'active'],
    ['per_page=101', 'per_page'],
    ['per_page=0', 'per_page'],
    ['page=-1', 'page'],
    ['search[]=invalid', 'search'],
]);

it('reserva nombres y códigos incluso después del borrado lógico', function (string $model, string $table) {
    $existing = $model::factory()->create(['name' => 'Reservado', 'code' => 'RES-01']);
    $other = $model::factory()->create();
    $existing->delete();

    $this->actingAs($this->territoryUser, 'api');
    $this->postJson('/api/admin/'.$table, ['name' => 'Reservado'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson('/api/admin/'.$table, ['name' => 'Nuevo', 'code' => 'res-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson("/api/admin/{$table}/{$other->id}", ['code' => 'RES-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
})->with('territorial catalogs');

it('impide eliminar catálogos con municipios activos o en papelera', function (string $model, string $table, string $relation, bool $trashed) {
    $catalog = $model::factory()->create();
    $municipality = Municipality::factory()->for($catalog, $relation)->create();

    if ($trashed) {
        $municipality->delete();
    }

    $this->actingAs($this->territoryUser, 'api')
        ->deleteJson("/api/admin/{$table}/{$catalog->id}")
        ->assertConflict();

    expect($catalog->fresh()->trashed())->toBeFalse();
})->with('territorial catalogs')->with([false, true]);

it('mantiene el acceso de consulta para los roles existentes', function (string $role) {
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user, 'api')
        ->getJson('/api/admin/regions')->assertOk();
    $this->postJson('/api/admin/regions', ['name' => 'Sin permiso'])
        ->assertForbidden();
})->with(['director', 'responsable', 'tecnico']);
