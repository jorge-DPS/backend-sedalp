<?php

use App\Enums\Auth\UserStatus;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->municipality = Municipality::factory()->create();
    $this->poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $this->document = MunicipalPoaDocument::factory()->forPoa($this->poa)->create();
    $this->authority = MunicipalAuthority::factory()->for($this->municipality)->create();
});

it('protege todas las rutas municipales con autenticación y permisos', function (string $method, string $path): void {
    $path = strtr($path, ['{poa}' => $this->poa->id, '{doc}' => $this->document->id, '{authority}' => $this->authority->id]);
    $url = "/api/admin/municipalities/{$this->municipality->id}/".$path;
    $this->json($method, $url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->json($method, $url)->assertForbidden();
})->with([
    ['GET', 'poas'], ['POST', 'poas'], ['GET', 'poas/{poa}'], ['PATCH', 'poas/{poa}'], ['PUT', 'poas/{poa}'],
    ['GET', 'poas/{poa}/documents'], ['POST', 'poas/{poa}/documents'], ['GET', 'poas/{poa}/documents/{doc}'],
    ['GET', 'poas/{poa}/documents/{doc}/file'], ['PUT', 'poas/{poa}/documents/{doc}/publication'],
    ['GET', 'authorities'], ['POST', 'authorities'], ['GET', 'authorities/{authority}'],
    ['PATCH', 'authorities/{authority}'], ['PUT', 'authorities/{authority}'],
    ['PUT', 'authorities/{authority}/current'], ['PUT', 'authorities/{authority}/publication'],
    ['GET', 'authorities/{authority}/photo'], ['POST', 'authorities/{authority}/photo'],
]);

it('rechaza cuentas suspendidas aunque tengan un rol autorizado', function (): void {
    $user = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api')->getJson("/api/admin/municipalities/{$this->municipality->id}/poas")->assertForbidden();
});

it('impide acceder a recursos de otro municipio o POA mediante sus identificadores', function (string $method, string $path): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $other = Municipality::factory()->create();
    $path = strtr($path, ['{poa}' => $this->poa->id, '{doc}' => $this->document->id, '{authority}' => $this->authority->id]);

    $this->actingAs($user, 'api')->json($method, "/api/admin/municipalities/{$other->id}/".$path)->assertNotFound();
})->with([
    ['GET', 'poas/{poa}'], ['PATCH', 'poas/{poa}'],
    ['GET', 'poas/{poa}/documents'], ['POST', 'poas/{poa}/documents'],
    ['GET', 'poas/{poa}/documents/{doc}'], ['GET', 'poas/{poa}/documents/{doc}/file'], ['PUT', 'poas/{poa}/documents/{doc}/publication'],
    ['GET', 'authorities/{authority}'], ['PATCH', 'authorities/{authority}'],
    ['PUT', 'authorities/{authority}/current'], ['PUT', 'authorities/{authority}/publication'],
    ['GET', 'authorities/{authority}/photo'], ['POST', 'authorities/{authority}/photo'],
]);

it('valida también el padre inmediato del documento dentro del mismo municipio', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $otherPoa = MunicipalPoa::factory()->for($this->municipality)->create(['fiscal_year' => 2028]);
    $this->actingAs($user, 'api')->getJson("/api/admin/municipalities/{$this->municipality->id}/poas/{$otherPoa->id}/documents/{$this->document->id}")
        ->assertNotFound();
});

it('mantiene separados los permisos de carga y publicación', function (): void {
    $user = User::factory()->create();
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api')->putJson("/api/admin/municipalities/{$this->municipality->id}/poas/{$this->poa->id}/documents/{$this->document->id}/publication")
        ->assertForbidden();
    $this->putJson("/api/admin/municipalities/{$this->municipality->id}/authorities/{$this->authority->id}/publication", ['is_published' => true])
        ->assertForbidden();
});
