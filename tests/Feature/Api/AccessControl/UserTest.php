<?php

use App\Models\People\OrganizationalUnit;
use App\Models\People\Position;
use App\Models\People\Profession;
use App\Models\People\StaffMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->managerRole = Role::create([
        'name' => 'user_manager_test',
        'guard_name' => 'api',
    ]);

    $this->managerRole->givePermissionTo([
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.assign',
    ]);

    $this->manager = User::factory()->create([
        'email' => 'manager@test.com',
    ]);

    $this->manager->assignRole($this->managerRole);

    $this->organizationalUnit = OrganizationalUnit::create([
        'name' => 'Unidad Usuarios Test',
        'code' => 'USERS_TEST',
        'active' => true,
    ]);

    $this->position = Position::create([
        'name' => 'Cargo Usuarios Test',
        'active' => true,
    ]);

    $this->profession = Profession::create([
        'name' => 'Profesión Usuarios Test',
        'active' => true,
    ]);
});

function createStaffForUserTest(
    $test,
    string $ci = '90000001',
    string $email = 'personal@test.com'
): StaffMember {
    return StaffMember::create([
        'first_names' => 'Personal Test',
        'paternal_surname' => 'Usuario',
        'maternal_surname' => null,
        'birth_date' => '1995-01-01',
        'ci' => $ci,
        'ci_complement' => null,
        'phone' => '70000000',
        'email' => $email,
        'organizational_unit_id' => $test->organizationalUnit->id,
        'position_id' => $test->position->id,
        'profession_id' => $test->profession->id,
        'active' => true,
    ]);
}

it('rechaza listar usuarios sin autenticación', function () {
    $this->getJson('/api/admin/users')
        ->assertUnauthorized();
});

it('rechaza listar usuarios sin permiso', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/users')
        ->assertForbidden();
});

it('crea un usuario vinculado a personal activo', function () {
    $staff = createStaffForUserTest($this);

    $response = $this
        ->actingAs($this->manager, 'api')
        ->postJson('/api/admin/users', [
            'staff_member_id' => $staff->id,
            'email' => 'nuevo.usuario@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'tecnico',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath(
            'data.email',
            'nuevo.usuario@test.com'
        );

    $this->assertDatabaseHas('users', [
        'staff_member_id' => $staff->id,
        'email' => 'nuevo.usuario@test.com',
    ]);

    $user = User::where(
        'email',
        'nuevo.usuario@test.com'
    )->firstOrFail();

    expect($user->hasRole('tecnico'))
        ->toBeTrue();
});

it('rechaza una contraseña débil al crear usuario', function () {
    $staff = createStaffForUserTest($this);

    $this
        ->actingAs($this->manager, 'api')
        ->postJson('/api/admin/users', [
            'staff_member_id' => $staff->id,
            'email' => 'debil@test.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'tecnico',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('rechaza crear dos usuarios para el mismo personal', function () {
    $staff = createStaffForUserTest($this);

    User::factory()->create([
        'staff_member_id' => $staff->id,
        'email' => 'primero@test.com',
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->postJson('/api/admin/users', [
            'staff_member_id' => $staff->id,
            'email' => 'segundo@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'tecnico',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_member_id');
});

it('rechaza crear usuario para personal inactivo', function () {
    $staff = createStaffForUserTest(
        $this,
        '90000002',
        'inactivo@test.com'
    );

    $staff->update([
        'active' => false,
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->postJson('/api/admin/users', [
            'staff_member_id' => $staff->id,
            'email' => 'usuario.inactivo@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'tecnico',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_member_id');
});

it('actualiza el correo de un usuario', function () {
    $target = User::factory()->create([
        'email' => 'anterior@test.com',
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->patchJson(
            "/api/admin/users/{$target->id}",
            [
                'email' => 'actualizado@test.com',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'email' => 'actualizado@test.com',
    ]);
});

it('cambia el rol de un usuario', function () {
    $target = User::factory()->create();

    $target->assignRole('tecnico');

    $this
        ->actingAs($this->manager, 'api')
        ->putJson(
            "/api/admin/users/{$target->id}/role",
            [
                'role' => 'director',
            ]
        )
        ->assertOk();

    $target->refresh();

    expect($target->hasRole('director'))
        ->toBeTrue();

    expect($target->hasRole('tecnico'))
        ->toBeFalse();
});

it('impide que un usuario elimine su propia cuenta', function () {
    $this
        ->actingAs($this->manager, 'api')
        ->deleteJson(
            "/api/admin/users/{$this->manager->id}"
        )
        ->assertUnprocessable();

    expect(
        User::withTrashed()
            ->find($this->manager->id)
            ?->trashed()
    )->toBeFalse();
});

it('impide a un usuario normal eliminar un super_admin', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin@test.com',
    ]);

    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($this->manager, 'api')
        ->deleteJson(
            "/api/admin/users/{$superAdmin->id}"
        )
        ->assertForbidden();

    expect(
        User::withTrashed()
            ->find($superAdmin->id)
            ?->trashed()
    )->toBeFalse();
});

it('elimina un usuario mediante soft delete', function () {
    $target = User::factory()->create([
        'email' => 'eliminar@test.com',
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->deleteJson(
            "/api/admin/users/{$target->id}"
        )
        ->assertNoContent();

    $this->assertSoftDeleted('users', [
        'id' => $target->id,
    ]);
});

it('impide a un usuario normal modificar las credenciales de un superadmin', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin.update@test.com',
    ]);

    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($this->manager, 'api')
        ->patchJson(
            "/api/admin/users/{$superAdmin->id}",
            [
                'email' => 'modificado@test.com',
            ]
        )
        ->assertForbidden();

    $this->assertDatabaseHas('users', [
        'id' => $superAdmin->id,
        'email' => 'superadmin.update@test.com',
    ]);
});

it('impide a un usuario normal degradar un superadmin', function () {
    $superAdmin = User::factory()->create();

    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($this->manager, 'api')
        ->putJson(
            "/api/admin/users/{$superAdmin->id}/role",
            [
                'role' => 'tecnico',
            ]
        )
        ->assertForbidden();

    $superAdmin->refresh();

    expect(
        $superAdmin->hasRole('super_admin')
    )->toBeTrue();
});

it('impide que un superadmin se quite su propio rol', function () {
    $superAdmin = User::factory()->create();

    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($superAdmin, 'api')
        ->putJson(
            "/api/admin/users/{$superAdmin->id}/role",
            [
                'role' => 'tecnico',
            ]
        )
        ->assertUnprocessable();

    $superAdmin->refresh();

    expect(
        $superAdmin->hasRole('super_admin')
    )->toBeTrue();
});

it('permite que un superadmin modifique a otro superadmin', function () {
    $actor = User::factory()->create([
        'email' => 'superadmin.actor@test.com',
    ]);

    $target = User::factory()->create([
        'email' => 'superadmin.target@test.com',
    ]);

    $actor->assignRole('super_admin');
    $target->assignRole('super_admin');

    $this
        ->actingAs($actor, 'api')
        ->patchJson(
            "/api/admin/users/{$target->id}",
            [
                'email' => 'superadmin.nuevo@test.com',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'email' => 'superadmin.nuevo@test.com',
    ]);
});
