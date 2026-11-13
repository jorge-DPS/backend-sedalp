<?php

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->url = "/api/admin/municipalities/{$this->municipality->id}/authorities";
    $this->term = MunicipalTerm::factory()->for($this->municipality)->create();
});

it('registra alcalde y varios concejales actuales sin publicarlos automáticamente', function (): void {
    $id = $this->postJson($this->url, ['municipal_term_id' => $this->term->id, 'full_name' => 'Alcalde de prueba', 'role' => 'mayor', 'is_current' => true])
        ->assertCreated()->assertJsonPath('data.is_current', true)->assertJsonPath('data.is_published', false)
        ->assertJsonMissingPath('data.photo_path')->json('data.id');
    foreach (['Concejal A', 'Concejal B'] as $name) {
        $this->postJson($this->url, ['municipal_term_id' => $this->term->id, 'full_name' => $name, 'role' => 'council_member', 'is_current' => true])->assertCreated();
    }
    $this->getJson($this->url.'?role=council_member&is_current=true&is_published=false&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.role', 'mayor');
    $this->patchJson($this->url.'/'.$id, ['position_title' => 'Alcalde municipal'])->assertOk();
    $this->putJson($this->url.'/'.$id, ['notes' => 'Fuente verificada'])->assertOk();
    $this->getJson($this->url.'?search=alcalde')->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson($this->url.'/'.$id)->assertMethodNotAllowed();
});

it('rechaza un segundo alcalde actual sin eliminar al primero', function (): void {
    $previous = MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->create();
    $this->postJson($this->url, ['municipal_term_id' => $this->term->id, 'full_name' => 'Segundo alcalde', 'role' => 'mayor', 'is_current' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('is_current');
    $candidate = MunicipalAuthority::factory()->for($this->municipality)->mayor()->create();
    $this->patchJson($this->url."/{$candidate->id}", ['is_current' => true])->assertUnprocessable();
    expect($previous->fresh()->is_current)->toBeTrue()->and($this->municipality->municipalAuthorities()->count())->toBe(2);
});

it('valida fechas roles y atributos del servidor', function (array $changes, string $field): void {
    $this->postJson($this->url, ['municipal_term_id' => $this->term->id, 'full_name' => 'Autoridad', 'role' => 'mayor', ...$changes])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->municipality->municipalAuthorities()->exists())->toBeFalse();
})->with([
    [['full_name' => null], 'full_name'], [['role' => 'president'], 'role'],
    [['term_start' => '2026-02-30'], 'term_start'],
    [['term_start' => '2025-01-02', 'term_end' => '2025-01-01'], 'term_end'],
    [['is_current' => true, 'term_start' => '2090-01-01'], 'is_current'],
    [['is_current' => true, 'term_end' => '2000-01-01'], 'is_current'],
    [['municipality_id' => 999999999], 'municipality_id'], [['photo_path' => '../secret.jpg'], 'photo_path'],
    [['is_published' => true], 'is_published'], [['is_published' => null], 'is_published'],
]);

it('valida períodos combinando datos vigentes con actualizaciones parciales', function (): void {
    $authority = MunicipalAuthority::factory()->for($this->municipality)->create(['term_start' => '2020-01-01', 'term_end' => '2025-01-01']);
    $this->patchJson($this->url."/{$authority->id}", ['term_start' => '2026-01-01'])->assertUnprocessable()->assertJsonValidationErrors('term_end');
    $this->patchJson($this->url."/{$authority->id}", ['role' => 'mayor'])->assertUnprocessable()->assertJsonValidationErrors('role');
    expect($authority->fresh()->term_start->toDateString())->toBe('2020-01-01');
});

it('realiza una transición explícita entre alcaldes y conserva publicación e historial', function (): void {
    $previous = MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->published()->create(['term_start' => today()->subYears(5)->toDateString()]);
    $candidate = MunicipalAuthority::factory()->for($this->municipality)->mayor()->create(['term_start' => today()->toDateString()]);
    $data = ['previous_authority_id' => $previous->id, 'previous_term_end' => today()->subDay()->toDateString()];
    $this->putJson($this->url."/{$candidate->id}/current", $data)->assertOk()
        ->assertJsonPath('data.is_current', true)->assertJsonPath('data.is_published', false);
    $this->putJson($this->url."/{$candidate->id}/current", $data)->assertOk();

    expect($previous->fresh()->is_current)->toBeFalse()->and($previous->fresh()->is_published)->toBeTrue()
        ->and($previous->fresh()->term_end->toDateString())->toBe($data['previous_term_end'])
        ->and($this->municipality->municipalAuthorities()->where('is_current', true)->count())->toBe(1)
        ->and($this->municipality->municipalAuthorities()->count())->toBe(2);
});

it('rechaza transiciones desactualizadas o con períodos inválidos sin cambiar al alcalde vigente', function (string $case): void {
    $previous = MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->create(['term_start' => '2020-01-01']);
    $candidate = MunicipalAuthority::factory()->for($this->municipality)->mayor()->create(['term_start' => today()->toDateString()]);
    $data = ['previous_authority_id' => $previous->id, 'previous_term_end' => today()->subDay()->toDateString()];
    if ($case === 'stale') {
        $data['previous_authority_id'] = 999999999;
    } elseif ($case === 'overlap') {
        $data['previous_term_end'] = today()->toDateString();
    } else {
        $candidate->update(['term_start' => null]);
    }
    $this->putJson($this->url."/{$candidate->id}/current", $data)->assertUnprocessable();
    expect($previous->fresh()->is_current)->toBeTrue()->and($candidate->fresh()->is_current)->toBeFalse();
})->with(['stale', 'overlap', 'unknown_start']);

it('revierte la transición completa si falla guardar al nuevo alcalde', function (): void {
    $previous = MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->create(['term_start' => '2020-01-01']);
    $candidate = MunicipalAuthority::factory()->for($this->municipality)->mayor()->create(['term_start' => today()->toDateString()]);
    $event = 'eloquent.updating: '.MunicipalAuthority::class;
    Event::listen($event, function (MunicipalAuthority $authority) use ($candidate): void {
        if ($authority->id === $candidate->id) {
            throw new RuntimeException('Fallo de transición.');
        }
    });
    try {
        $this->putJson($this->url."/{$candidate->id}/current", [
            'previous_authority_id' => $previous->id, 'previous_term_end' => today()->subDay()->toDateString(),
        ])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }
    expect($previous->fresh()->is_current)->toBeTrue()->and($previous->fresh()->term_end)->toBeNull()
        ->and($candidate->fresh()->is_current)->toBeFalse();
});

it('publica y despublica autoridades históricas sin alterar su estado actual', function (): void {
    $authority = MunicipalAuthority::factory()->for($this->municipality)->create(['term_start' => '2020-01-01', 'term_end' => '2021-01-01']);
    $this->putJson($this->url."/{$authority->id}/publication", ['is_published' => true])->assertForbidden();
    $reviewer = User::factory()->create();
    $reviewer->assignRole('responsable');
    $this->actingAs($reviewer, 'api')->putJson($this->url."/{$authority->id}/publication", ['is_published' => true])
        ->assertOk()->assertJsonPath('data.is_published', true)->assertJsonPath('data.is_current', false);
    $this->putJson($this->url."/{$authority->id}/publication", ['is_published' => false])->assertOk()->assertJsonPath('data.is_published', false);
    $this->putJson($this->url."/{$authority->id}/publication", ['is_published' => true, 'is_current' => true])->assertUnprocessable();
});

it('valida los filtros y rechaza escritura en municipios inactivos o eliminados', function (): void {
    $this->getJson($this->url.'?per_page=101')->assertUnprocessable();
    $this->getJson($this->url.'?role=unknown')->assertUnprocessable();
    $this->getJson($this->url.'?is_current=invalid')->assertUnprocessable();
    $this->municipality->update(['active' => false]);
    $this->postJson($this->url, ['municipal_term_id' => $this->term->id, 'full_name' => 'Autoridad', 'role' => 'mayor'])->assertUnprocessable();
    $this->municipality->delete();
    $this->getJson($this->url)->assertNotFound();
});
