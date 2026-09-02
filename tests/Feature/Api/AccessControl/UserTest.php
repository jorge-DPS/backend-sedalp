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

function integratedUserPayload(
    $test,
    string $ci = '90000010',
    string $email = 'usuario.integrado@test.com'
): array {
    return [
        'staff_member' => [
            'first_names' => 'María Elena',
            'paternal_surname' => 'Quispe',
            'maternal_surname' => 'Mamani',
            'birth_date' => '1992-04-15',
            'ci' => $ci,
            'ci_complement' => null,
            'phone' => '71234567',
            'email' => 'maria.personal@test.com',
            'organizational_unit_id' => $test->organizationalUnit->id,
            'position_id' => $test->position->id,
            'profession_id' => $test->profession->id,
        ],
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'tecnico',
    ];
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

    $user = User::where('email', 'nuevo.usuario@test.com')->firstOrFail();

    expect($user->hasRole('tecnico'))
        ->toBeTrue();
});

it('permite al superadmin crear personal y usuario en una sola operación', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin.integrado@test.com',
    ]);
    $superAdmin->assignRole('super_admin');

    $response = $this
        ->actingAs($superAdmin, 'api')
        ->postJson(
            '/api/admin/users',
            integratedUserPayload($this)
        );

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'usuario.integrado@test.com')
        ->assertJsonPath(
            'data.staff_member.first_names',
            'María Elena'
        )
        ->assertJsonPath(
            'data.staff_member.position.name',
            $this->position->name
        )
        ->assertJsonPath(
            'data.staff_member.profession.name',
            $this->profession->name
        );

    $staffMember = StaffMember::query()
        ->where('ci', '90000010')
        ->firstOrFail();

    $this->assertDatabaseHas('users', [
        'staff_member_id' => $staffMember->id,
        'email' => 'usuario.integrado@test.com',
    ]);
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

it('rechaza crear una cuenta sin personal asociado', function () {
    $this
        ->actingAs($this->manager, 'api')
        ->postJson('/api/admin/users', [
            'email' => 'sin.personal@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'tecnico',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'staff_member_id',
            'staff_member',
        ]);
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

it('actualiza credenciales y datos personales desde el usuario', function () {
    $staffMember = createStaffForUserTest(
        $this,
        '90000011',
        'personal.actualizar@test.com'
    );

    $target = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'cuenta.anterior@test.com',
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->patchJson(
            "/api/admin/users/{$target->id}",
            [
                'email' => 'cuenta.actualizada@test.com',
                'staff_member' => [
                    'first_names' => 'Juan Carlos',
                    'paternal_surname' => 'Flores',
                    'phone' => '76543210',
                ],
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.staff_member.first_names',
            'Juan Carlos'
        )
        ->assertJsonPath(
            'data.staff_member.paternal_surname',
            'Flores'
        );

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'email' => 'cuenta.actualizada@test.com',
    ]);

    $this->assertDatabaseHas('staff_members', [
        'id' => $staffMember->id,
        'first_names' => 'Juan Carlos',
        'paternal_surname' => 'Flores',
        'phone' => '76543210',
    ]);
});

it('impide cambiar el estado laboral desde la edición general del usuario', function () {
    $staffMember = createStaffForUserTest(
        $this,
        '90000012',
        'personal.estado@test.com'
    );

    $target = User::factory()->create([
        'staff_member_id' => $staffMember->id,
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->patchJson(
            "/api/admin/users/{$target->id}",
            [
                'staff_member' => [
                    'active' => false,
                ],
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_member.active');
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
            "/api/admin/users/{$this->manager->id}",
            [
                'reason' => 'Prueba de autoeliminación.',
            ]
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
            "/api/admin/users/{$superAdmin->id}",
            [
                'reason' => 'Prueba de protección privilegiada.',
            ]
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
            "/api/admin/users/{$target->id}",
            [
                'reason' => 'Baja administrativa de prueba.',
            ]
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

it('lista usuarios con permiso', function () {
    User::factory()->create([
        'email' => 'usuario.listado@test.com',
    ]);

    $this
        ->actingAs($this->manager, 'api')
        ->getJson('/api/admin/users')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'email',
                    'staff_member',
                    'roles',
                    'permissions',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('normaliza y aplica el filtro search al listar usuarios', function () {
    $matchingUser = User::factory()->create([
        'email' => 'usuario.buscar@test.com',
    ]);

    $otherUser = User::factory()->create([
        'email' => 'otro.usuario@test.com',
    ]);

    $response = $this
        ->actingAs($this->manager, 'api')
        ->getJson(
            '/api/admin/users?search=%20%20usuario.buscar%20%20'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($matchingUser->id)
        ->not->toContain($otherUser->id);
});

it('busca usuarios por nombres cargo y profesión', function () {
    $staffMember = createStaffForUserTest(
        $this,
        '90000013',
        'personal.busqueda@test.com'
    );

    $staffMember->update([
        'first_names' => 'Nombre Encontrable',
    ]);

    $matchingUser = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'cuenta.busqueda@test.com',
    ]);

    foreach (
        ['Nombre Encontrable', $this->position->name, $this->profession->name] as $search
    ) {
        $response = $this
            ->actingAs($this->manager, 'api')
            ->getJson(
                '/api/admin/users?search='.urlencode($search)
            )
            ->assertOk();

        expect(collect($response->json('data'))->pluck('id'))
            ->toContain($matchingUser->id);
    }
});

it('filtra usuarios por rol estado cargo y profesión', function () {
    $staffMember = createStaffForUserTest(
        $this,
        '90000014',
        'personal.filtros@test.com'
    );

    $matchingUser = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'cuenta.filtros@test.com',
    ]);
    $matchingUser->assignRole('tecnico');

    $response = $this
        ->actingAs($this->manager, 'api')
        ->getJson(
            '/api/admin/users?role=tecnico'
            .'&account_status=active'
            .'&staff_active=true'
            ."&position_id={$this->position->id}"
            ."&profession_id={$this->profession->id}"
        )
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))
        ->toContain($matchingUser->id);
});

it('rechaza un search con tipo inválido al listar usuarios', function () {
    $this
        ->actingAs($this->manager, 'api')
        ->getJson(
            '/api/admin/users?search[]=usuario'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'search'
        );
});

it('rechaza paginación inválida al listar usuarios', function () {
    foreach (['abc', '0', '101'] as $perPage) {
        $this
            ->actingAs($this->manager, 'api')
            ->getJson(
                "/api/admin/users?per_page={$perPage}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'per_page'
            );
    }
});

it('acepta el límite máximo de paginación al listar usuarios', function () {
    $this
        ->actingAs($this->manager, 'api')
        ->getJson(
            '/api/admin/users?per_page=100'
        )
        ->assertOk()
        ->assertJsonPath(
            'meta.per_page',
            100
        );
});
