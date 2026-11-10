<?php

use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->url = '/api/admin/investment/projects/'.$this->project->id.'/publication';
});

it('exige autenticación y permiso específico para publicar proyectos', function (): void {
    $this->putJson($this->url, ['is_published' => true])->assertUnauthorized();
    foreach (['tecnico', 'director', 'comunicador'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user, 'api')->putJson($this->url, ['is_published' => true])->assertForbidden();
    }
    expect($this->project->fresh()->published_at)->toBeNull();
});

it('publica y retira proyectos con sus indicadores y preserva sus relaciones y presupuestos', function (): void {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('responsable');
    $this->actingAs($reviewer, 'api');
    $budget = ProjectBudget::factory()->for($this->project)->reported('100.00')->create(['fiscal_year' => 2027]);
    $before = $budget->fresh()->getAttributes();
    $this->getJson('/api/public/investment/projects/'.$this->project->id)->assertNotFound();
    $response = $this->putJson($this->url, ['is_published' => true])->assertOk()->assertJsonPath('data.is_published', true);
    $timestamp = $response->json('data.published_at');
    $this->getJson('/api/public/investment/projects/'.$this->project->id.'?fiscal_year=2027')->assertOk()
        ->assertJsonPath('data.annual_budget.amount_bob', '100.00');
    $this->getJson('/api/public/investment/dashboard?fiscal_year=2027')->assertOk()
        ->assertJsonPath('data.project_count', 1)->assertJsonPath('data.annual_budget.known_amount_bob', '100.00');
    $this->travel(5)->minutes();
    $this->putJson($this->url, ['is_published' => true])->assertOk()->assertJsonPath('data.published_at', $timestamp);
    $this->putJson($this->url, ['is_published' => false])->assertOk()->assertJsonPath('data.published_at', null);
    $this->getJson('/api/public/investment/projects/'.$this->project->id)->assertNotFound();
    $this->getJson('/api/public/investment/dashboard')->assertOk()->assertJsonPath('data.project_count', 0);
    expect($budget->fresh()->getAttributes())->toBe($before)
        ->and($this->project->fresh()->institution_id)->toBe($this->project->institution_id);
    $this->travelBack();
});

it('impide publicar mediante creación o edición ordinaria incluso con valores nulos', function (string $field): void {
    $technician = User::factory()->create();
    $technician->assignRole('tecnico');
    $this->actingAs($technician, 'api');
    $payload = ['name' => 'Nuevo proyecto', 'institution_id' => $this->project->institution_id];
    $this->postJson('/api/admin/investment/projects', [...$payload, $field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->patchJson('/api/admin/investment/projects/'.$this->project->id, [$field => null])->assertUnprocessable()->assertJsonValidationErrors($field);
    $id = $this->postJson('/api/admin/investment/projects', $payload)->assertCreated()->assertJsonPath('data.is_published', false)->json('data.id');
    $this->getJson('/api/public/investment/projects/'.$id)->assertNotFound();
})->with(['published_at', 'is_published']);

it('valida la operación y no admite programar fechas proporcionadas por el cliente', function (): void {
    $user = User::factory()->create();
    $user->assignRole('responsable');
    $this->actingAs($user, 'api');
    $this->putJson($this->url, [])->assertUnprocessable()->assertJsonValidationErrors('is_published');
    $this->putJson($this->url, ['is_published' => 'yes'])->assertUnprocessable();
    $this->putJson($this->url, ['is_published' => true, 'published_at' => null])->assertUnprocessable();
    $this->project->delete();
    $this->putJson($this->url, ['is_published' => true])->assertNotFound();
});
