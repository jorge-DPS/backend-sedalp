<?php

use App\Models\User;
use Database\Seeders\NewsPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(NewsPermissionsSeeder::class);
});

it('rechaza el catálogo de roles sin autenticación', function () {
    $this->getJson('/api/admin/access/roles')
        ->assertUnauthorized();
});

it('rechaza el catálogo de roles si el usuario no tiene permiso', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/access/roles')
        ->assertForbidden();
});

it('permite consultar roles a un usuario con roles.view', function () {
    $role = Role::create([
        'name' => 'auditor_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo('roles.view');

    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/access/roles');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'permissions',
                ],
            ],
        ]);
});

it('oculta super_admin del catálogo para un usuario normal', function () {
    $role = Role::create([
        'name' => 'auditor_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo('roles.view');

    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/access/roles');

    $response->assertOk();

    $roleNames = collect(
        $response->json('data')
    )->pluck('name');

    expect($roleNames)
        ->not->toContain('super_admin');
});

it('permite al super_admin consultar el catálogo de roles', function () {
    $user = User::factory()->create();

    $user->assignRole('super_admin');

    $response = $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/access/roles');

    $response->assertOk();

    $roleNames = collect(
        $response->json('data')
    )->pluck('name');

    expect($roleNames)
        ->toContain('super_admin');
});

it('permite al super_admin consultar permisos mediante el bypass global', function () {
    $user = User::factory()->create();

    $user->assignRole('super_admin');

    $response = $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/access/permissions');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                ],
            ],
        ]);
});

it('permite consultar permisos a quien tiene permissions.view', function () {
    $role = Role::create([
        'name' => 'permissions_auditor_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo('permissions.view');

    $user = User::factory()->create();
    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/access/permissions')
        ->assertOk();
});

it('el comunicador conserva los permisos operativos de noticias', function () {
    $communicator = Role::findByName(
        'comunicador',
        'api'
    );

    expect($communicator->hasPermissionTo('news.view'))
        ->toBeTrue();

    expect($communicator->hasPermissionTo('news.create'))
        ->toBeTrue();

    expect($communicator->hasPermissionTo('news.update'))
        ->toBeTrue();

    expect($communicator->hasPermissionTo('news.delete'))
        ->toBeTrue();

    expect($communicator->hasPermissionTo('news.publish'))
        ->toBeTrue();
});

it('el comunicador no recibe permisos administrativos de papelera', function () {
    $communicator = Role::findByName(
        'comunicador',
        'api'
    );

    expect($communicator->hasPermissionTo('news.trash.view'))
        ->toBeFalse();

    expect($communicator->hasPermissionTo('news.restore'))
        ->toBeFalse();

    expect($communicator->hasPermissionTo('news.force_delete'))
        ->toBeFalse();
});
