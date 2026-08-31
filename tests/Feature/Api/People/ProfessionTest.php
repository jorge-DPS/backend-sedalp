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

    $this->professionRole = Role::create([
        'name' => 'profession_test',
        'guard_name' => 'api',
    ]);

    $this->professionRole->givePermissionTo([
        'professions.view',
        'professions.create',
        'professions.update',
        'professions.delete',
    ]);

    $this->user = User::factory()->create();

    $this->user->assignRole(
        $this->professionRole
    );
});

it('rechaza listar profesiones sin autenticación', function () {
    $this
        ->getJson('/api/admin/professions')
        ->assertUnauthorized();
});

it('rechaza listar profesiones sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/professions')
        ->assertForbidden();
});

it('lista profesiones con permiso', function () {
    Profession::create([
        'name' => 'Ingeniería Informática',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson('/api/admin/professions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'active',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('normaliza y aplica el filtro search de profesiones', function () {
    $matchingProfession = Profession::create([
        'name' => 'Ingeniería Informática',
        'active' => true,
    ]);

    $otherProfession = Profession::create([
        'name' => 'Derecho',
        'active' => true,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/professions?search=%20%20Informática%20%20'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($matchingProfession->id)
        ->not->toContain($otherProfession->id);
});

it('filtra profesiones inactivas', function () {
    $activeProfession = Profession::create([
        'name' => 'Ingeniería Civil',
        'active' => true,
    ]);

    $inactiveProfession = Profession::create([
        'name' => 'Arquitectura',
        'active' => false,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/professions?active=false'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($inactiveProfession->id)
        ->not->toContain($activeProfession->id);
});

it('rechaza un filtro active inválido en profesiones', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/professions?active=invalido'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('active');
});

it('rechaza paginación inválida en profesiones', function () {
    foreach (['abc', '0', '101'] as $perPage) {
        $this
            ->actingAs($this->user, 'api')
            ->getJson(
                "/api/admin/professions?per_page={$perPage}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
});

it('acepta el límite máximo de paginación en profesiones', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/professions?per_page=100'
        )
        ->assertOk()
        ->assertJsonPath(
            'meta.per_page',
            100
        );
});

it('crea una profesión normalizando el nombre', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/professions', [
            'name' => '  Ingeniería Ambiental  ',
        ])
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Ingeniería Ambiental'
        )
        ->assertJsonPath(
            'data.active',
            true
        );

    $this->assertDatabaseHas('professions', [
        'id' => $response->json('data.id'),
        'name' => 'Ingeniería Ambiental',
        'active' => true,
    ]);
});

it('permite crear una profesión inactiva', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/professions', [
            'name' => 'Profesión Inactiva',
            'active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath(
            'data.active',
            false
        );

    $this->assertDatabaseHas('professions', [
        'id' => $response->json('data.id'),
        'name' => 'Profesión Inactiva',
        'active' => false,
    ]);
});

it('rechaza crear una profesión sin permiso', function () {
    $role = Role::create([
        'name' => 'profession_view_only_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'professions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->postJson('/api/admin/professions', [
            'name' => 'Profesión Restringida',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('professions', [
        'name' => 'Profesión Restringida',
    ]);
});

it('rechaza un nombre duplicado al crear una profesión', function () {
    Profession::create([
        'name' => 'Ingeniería de Sistemas',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/professions', [
            'name' => 'Ingeniería de Sistemas',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('rechaza un nombre mayor a 150 caracteres', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/professions', [
            'name' => str_repeat('A', 151),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('muestra una profesión', function () {
    $profession = Profession::create([
        'name' => 'Auditoría',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            "/api/admin/professions/{$profession->id}"
        )
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $profession->id
        )
        ->assertJsonPath(
            'data.name',
            'Auditoría'
        )
        ->assertJsonPath(
            'data.active',
            true
        );
});

it('actualiza una profesión normalizando el nombre', function () {
    $profession = Profession::create([
        'name' => 'Profesión Original',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/professions/{$profession->id}",
            [
                'name' => '  Profesión Actualizada  ',
                'active' => false,
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Profesión Actualizada'
        )
        ->assertJsonPath(
            'data.active',
            false
        );

    $this->assertDatabaseHas('professions', [
        'id' => $profession->id,
        'name' => 'Profesión Actualizada',
        'active' => false,
    ]);
});

it('permite actualizar una profesión conservando su mismo nombre', function () {
    $profession = Profession::create([
        'name' => 'Ingeniería Industrial',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/professions/{$profession->id}",
            [
                'name' => 'Ingeniería Industrial',
                'active' => false,
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Ingeniería Industrial'
        )
        ->assertJsonPath(
            'data.active',
            false
        );
});

it('rechaza usar el nombre de otra profesión al actualizar', function () {
    $firstProfession = Profession::create([
        'name' => 'Primera Profesión',
        'active' => true,
    ]);

    Profession::create([
        'name' => 'Segunda Profesión',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/professions/{$firstProfession->id}",
            [
                'name' => 'Segunda Profesión',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseHas('professions', [
        'id' => $firstProfession->id,
        'name' => 'Primera Profesión',
    ]);
});

it('rechaza actualizar una profesión sin permiso', function () {
    $profession = Profession::create([
        'name' => 'Profesión Protegida',
        'active' => true,
    ]);

    $role = Role::create([
        'name' => 'profession_update_denied_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'professions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->patchJson(
            "/api/admin/professions/{$profession->id}",
            [
                'name' => 'Profesión Modificada',
            ]
        )
        ->assertForbidden();

    $this->assertDatabaseHas('professions', [
        'id' => $profession->id,
        'name' => 'Profesión Protegida',
    ]);
});

it('elimina una profesión mediante soft delete', function () {
    $profession = Profession::create([
        'name' => 'Profesión Eliminable',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/professions/{$profession->id}"
        )
        ->assertNoContent();

    $this->assertSoftDeleted('professions', [
        'id' => $profession->id,
    ]);
});

it('rechaza eliminar una profesión sin permiso', function () {
    $profession = Profession::create([
        'name' => 'Profesión No Eliminable',
        'active' => true,
    ]);

    $role = Role::create([
        'name' => 'profession_delete_denied_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'professions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->deleteJson(
            "/api/admin/professions/{$profession->id}"
        )
        ->assertForbidden();

    $this->assertDatabaseHas('professions', [
        'id' => $profession->id,
        'deleted_at' => null,
    ]);
});

it('impide eliminar una profesión con personal asociado', function () {
    $profession = Profession::create([
        'name' => 'Profesión con Personal',
        'active' => true,
    ]);

    $organizationalUnit = OrganizationalUnit::create([
        'name' => 'Unidad Profession Test',
        'code' => 'PROFESSION_TEST_UNIT',
        'active' => true,
    ]);

    $position = Position::create([
        'name' => 'Cargo Profession Test',
        'active' => true,
    ]);

    StaffMember::create([
        'first_names' => 'Juan Carlos',
        'paternal_surname' => 'Pérez',
        'maternal_surname' => 'Mamani',
        'ci' => '987654321',
        'organizational_unit_id' => $organizationalUnit->id,
        'position_id' => $position->id,
        'profession_id' => $profession->id,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/professions/{$profession->id}"
        )
        ->assertStatus(409)
        ->assertJsonPath(
            'message',
            'La profesión tiene personal asociado. Desactívela en lugar de eliminarla.'
        );

    $this->assertDatabaseHas('professions', [
        'id' => $profession->id,
        'deleted_at' => null,
    ]);
});
