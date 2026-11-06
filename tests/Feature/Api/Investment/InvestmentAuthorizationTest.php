<?php

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\UserStatus;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->project = Project::factory()->create();
    $this->source = InvestmentSource::factory()->create();
    $this->budget = ProjectBudget::factory()->for($this->project)->create();
});

it('exige autenticación y permiso para cada operación administrativa de inversión', function (string $method, string $path): void {
    $path = strtr($path, ['{project}' => $this->project->id, '{source}' => $this->source->id, '{budget}' => $this->budget->id]);
    $url = '/api/admin/investment/'.$path;

    $this->json($method, $url)->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->json($method, $url)->assertForbidden();
})->with([
    ['GET', 'catalogs'],
    ['GET', 'projects'], ['POST', 'projects'], ['GET', 'projects/{project}'],
    ['PATCH', 'projects/{project}'], ['PUT', 'projects/{project}'], ['DELETE', 'projects/{project}'],
    ['GET', 'sources'], ['POST', 'sources'], ['GET', 'sources/{source}'],
    ['PATCH', 'sources/{source}'], ['PUT', 'sources/{source}'],
    ['GET', 'projects/{project}/budgets'], ['POST', 'projects/{project}/budgets'],
    ['GET', 'projects/{project}/budgets/{budget}'],
    ['GET', 'projects/{project}/budgets/{budget}/revisions'], ['POST', 'projects/{project}/budgets/{budget}/revisions'],
]);

it('rechaza cuentas suspendidas aunque tengan todos los permisos', function (): void {
    $user = User::factory()->create(['account_status' => UserStatus::SUSPENDED]);
    $user->givePermissionTo(array_map(fn (PermissionName $permission): string => $permission->value, PermissionName::cases()));

    $this->actingAs($user, 'api')->getJson('/api/admin/investment/projects')->assertForbidden();
});

it('no confunde permisos de proyectos fuentes y presupuestos', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::INVESTMENT_PROJECTS_VIEW->value);
    $this->actingAs($user, 'api')
        ->getJson('/api/admin/investment/projects')->assertOk();
    $this->getJson('/api/admin/investment/sources')->assertForbidden();
    $this->getJson("/api/admin/investment/projects/{$this->project->id}/budgets")->assertForbidden();
    $this->patchJson("/api/admin/investment/projects/{$this->project->id}", ['name' => 'Intento'])->assertForbidden();
});

it('devuelve 404 para recursos inexistentes a usuarios autorizados', function (string $method, string $path): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $path = strtr($path, ['{project}' => $this->project->id]);

    $this->actingAs($user, 'api')->json($method, '/api/admin/investment/'.$path)->assertNotFound();
})->with([
    ['GET', 'projects/999999999'], ['PATCH', 'projects/999999999'], ['DELETE', 'projects/999999999'],
    ['GET', 'sources/999999999'], ['PATCH', 'sources/999999999'],
    ['GET', 'projects/999999999/budgets'], ['POST', 'projects/999999999/budgets'],
    ['GET', 'projects/{project}/budgets/999999999'],
    ['GET', 'projects/{project}/budgets/999999999/revisions'], ['POST', 'projects/{project}/budgets/999999999/revisions'],
]);
