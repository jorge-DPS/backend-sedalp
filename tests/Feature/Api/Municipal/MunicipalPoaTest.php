<?php

use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->url = "/api/admin/municipalities/{$this->municipality->id}/poas";
});

it('crea consulta actualiza y pagina POA municipales por gestión', function (): void {
    $id = $this->postJson($this->url, ['fiscal_year' => 2027, 'title' => 'POA 2027'])->assertCreated()
        ->assertJsonPath('data.municipality_id', $this->municipality->id)->assertJsonPath('data.documents_count', 0)->json('data.id');
    $this->postJson($this->url, ['fiscal_year' => 2028])->assertCreated();
    $this->getJson($this->url.'?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.fiscal_year', 2028);
    $this->getJson($this->url.'?fiscal_year=2027&search=poa')->assertOk()->assertJsonCount(1, 'data');
    $this->patchJson($this->url.'/'.$id, ['notes' => 'Fuente recibida'])->assertOk()->assertJsonPath('data.title', 'POA 2027');
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.notes', 'Fuente recibida');
    $this->putJson($this->url.'/'.$id, ['title' => 'POA revisado'])->assertOk();
});

it('rechaza duplicados del mismo municipio y permite la misma gestión en otro municipio', function (): void {
    $this->postJson($this->url, ['fiscal_year' => 2027])->assertCreated();
    $this->postJson($this->url, ['fiscal_year' => 2027])->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
    $other = Municipality::factory()->create();
    $this->postJson("/api/admin/municipalities/{$other->id}/poas", ['fiscal_year' => 2027])->assertCreated();
    expect(MunicipalPoa::count())->toBe(2);
});

it('conserva los documentos y rechaza cambios de identidad incluso con valores nulos', function (string $field): void {
    $poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $document = MunicipalPoaDocument::factory()->for($poa)->create();
    $before = $document->fresh()->getAttributes();
    $this->patchJson($this->url."/{$poa->id}", [$field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->patchJson($this->url."/{$poa->id}", ['title' => 'Metadatos actualizados'])->assertOk()->assertJsonPath('data.documents_count', 1);
    expect($document->fresh()->getAttributes())->toBe($before);
    $this->deleteJson($this->url."/{$poa->id}")->assertMethodNotAllowed();
})->with(['fiscal_year', 'municipality_id']);

it('valida los campos y la paginación de POA', function (array $payload, string $field): void {
    $this->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [[], 'fiscal_year'], [['fiscal_year' => 1899], 'fiscal_year'], [['fiscal_year' => 2101], 'fiscal_year'],
    [['fiscal_year' => 2027, 'title' => str_repeat('x', 256)], 'title'],
    [['fiscal_year' => 2027, 'municipality_id' => 999999999], 'municipality_id'],
]);

it('rechaza municipios inexistentes eliminados o inactivos sin borrar sus registros', function (): void {
    $this->postJson('/api/admin/municipalities/999999999/poas', ['fiscal_year' => 2027])->assertNotFound();
    $poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $this->municipality->update(['active' => false]);
    $this->postJson($this->url, ['fiscal_year' => 2027])->assertUnprocessable()->assertJsonValidationErrors('municipality_id');
    $this->patchJson($this->url."/{$poa->id}", ['title' => 'Intento'])->assertUnprocessable();
    $this->getJson($this->url)->assertOk()->assertJsonCount(1, 'data');
    $this->municipality->delete();
    $this->getJson($this->url)->assertNotFound();
    $this->assertModelExists($poa);
});

it('rechaza filtros o identificadores de POA inválidos', function (): void {
    $this->getJson($this->url.'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson($this->url.'?fiscal_year=1800')->assertUnprocessable();
    $this->getJson($this->url.'/999999999')->assertNotFound();
});
