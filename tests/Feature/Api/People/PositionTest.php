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

    $this->positionRole = Role::create([
        'name' => 'position_test',
        'guard_name' => 'api',
    ]);

    $this->positionRole->givePermissionTo([
        'positions.view',
        'positions.create',
        'positions.update',
        'positions.delete',
    ]);

    $this->user = User::factory()->create();

    $this->user->assignRole(
        $this->positionRole
    );
});

it('rechaza listar cargos sin autenticación', function () {
    $this
        ->getJson('/api/admin/positions')
        ->assertUnauthorized();
});

it('rechaza listar cargos sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/positions')
        ->assertForbidden();
});

it('lista cargos con permiso', function () {
    Position::create([
        'name' => 'Director Técnico',
        'description' => 'Dirección técnica institucional.',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson('/api/admin/positions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'active',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);
});

it('normaliza y aplica el filtro search de cargos', function () {
    $matchingPosition = Position::create([
        'name' => 'Director Técnico',
        'description' => null,
        'active' => true,
    ]);

    $otherPosition = Position::create([
        'name' => 'Profesional II',
        'description' => null,
        'active' => true,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/positions?search=%20%20Director%20%20'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($matchingPosition->id)
        ->not->toContain($otherPosition->id);
});

it('filtra cargos inactivos', function () {
    $activePosition = Position::create([
        'name' => 'Cargo Activo',
        'description' => null,
        'active' => true,
    ]);

    $inactivePosition = Position::create([
        'name' => 'Cargo Inactivo',
        'description' => null,
        'active' => false,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/positions?active=false'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($inactivePosition->id)
        ->not->toContain($activePosition->id);
});

it('rechaza un filtro active inválido en cargos', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/positions?active=invalido'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('active');
});

it('rechaza paginación inválida en cargos', function () {
    foreach (['abc', '0', '101'] as $perPage) {
        $this
            ->actingAs($this->user, 'api')
            ->getJson(
                "/api/admin/positions?per_page={$perPage}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
});

it('acepta el límite máximo de paginación en cargos', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/positions?per_page=100'
        )
        ->assertOk()
        ->assertJsonPath(
            'meta.per_page',
            100
        );
});

it('crea un cargo normalizando el nombre', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/positions', [
            'name' => '  Responsable Técnico  ',
            'description' => 'Responsable del área técnica.',
        ])
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Responsable Técnico'
        )
        ->assertJsonPath(
            'data.description',
            'Responsable del área técnica.'
        )
        ->assertJsonPath(
            'data.active',
            true
        );

    $this->assertDatabaseHas('positions', [
        'id' => $response->json('data.id'),
        'name' => 'Responsable Técnico',
        'description' => 'Responsable del área técnica.',
        'active' => true,
    ]);
});

it('rechaza crear un cargo sin permiso', function () {
    $role = Role::create([
        'name' => 'position_view_only_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'positions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->postJson('/api/admin/positions', [
            'name' => 'Cargo Restringido',
            'description' => null,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('positions', [
        'name' => 'Cargo Restringido',
    ]);
});

it('rechaza un nombre duplicado al crear un cargo', function () {
    Position::create([
        'name' => 'Profesional Técnico',
        'description' => null,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/positions', [
            'name' => 'Profesional Técnico',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('rechaza longitudes mayores a las permitidas al crear un cargo', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/positions', [
            'name' => str_repeat('A', 101),
            'description' => str_repeat('B', 151),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'description',
        ]);
});

it('muestra un cargo', function () {
    $position = Position::create([
        'name' => 'Jefe de Unidad',
        'description' => 'Jefatura institucional.',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            "/api/admin/positions/{$position->id}"
        )
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $position->id
        )
        ->assertJsonPath(
            'data.name',
            'Jefe de Unidad'
        )
        ->assertJsonPath(
            'data.description',
            'Jefatura institucional.'
        )
        ->assertJsonPath(
            'data.active',
            true
        );
});

it('actualiza un cargo normalizando el nombre', function () {
    $position = Position::create([
        'name' => 'Cargo Original',
        'description' => 'Descripción original.',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/positions/{$position->id}",
            [
                'name' => '  Cargo Actualizado  ',
                'description' => 'Descripción actualizada.',
                'active' => false,
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Cargo Actualizado'
        )
        ->assertJsonPath(
            'data.description',
            'Descripción actualizada.'
        )
        ->assertJsonPath(
            'data.active',
            false
        );

    $this->assertDatabaseHas('positions', [
        'id' => $position->id,
        'name' => 'Cargo Actualizado',
        'description' => 'Descripción actualizada.',
        'active' => false,
    ]);
});

it('permite actualizar un cargo conservando su mismo nombre', function () {
    $position = Position::create([
        'name' => 'Cargo Existente',
        'description' => 'Descripción original.',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/positions/{$position->id}",
            [
                'name' => 'Cargo Existente',
                'description' => 'Nueva descripción.',
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Cargo Existente'
        )
        ->assertJsonPath(
            'data.description',
            'Nueva descripción.'
        );
});

it('rechaza usar el nombre de otro cargo al actualizar', function () {
    $firstPosition = Position::create([
        'name' => 'Primer Cargo',
        'description' => null,
        'active' => true,
    ]);

    Position::create([
        'name' => 'Segundo Cargo',
        'description' => null,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/positions/{$firstPosition->id}",
            [
                'name' => 'Segundo Cargo',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseHas('positions', [
        'id' => $firstPosition->id,
        'name' => 'Primer Cargo',
    ]);
});

it('rechaza actualizar un cargo sin permiso', function () {
    $position = Position::create([
        'name' => 'Cargo Protegido',
        'description' => null,
        'active' => true,
    ]);

    $role = Role::create([
        'name' => 'position_update_denied_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'positions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->patchJson(
            "/api/admin/positions/{$position->id}",
            [
                'name' => 'Cargo Modificado',
            ]
        )
        ->assertForbidden();

    $this->assertDatabaseHas('positions', [
        'id' => $position->id,
        'name' => 'Cargo Protegido',
    ]);
});

it('elimina un cargo mediante soft delete', function () {
    $position = Position::create([
        'name' => 'Cargo Eliminable',
        'description' => null,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/positions/{$position->id}"
        )
        ->assertNoContent();

    $this->assertSoftDeleted('positions', [
        'id' => $position->id,
    ]);
});

it('rechaza eliminar un cargo sin permiso', function () {
    $position = Position::create([
        'name' => 'Cargo No Eliminable',
        'description' => null,
        'active' => true,
    ]);

    $role = Role::create([
        'name' => 'position_delete_denied_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'positions.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->deleteJson(
            "/api/admin/positions/{$position->id}"
        )
        ->assertForbidden();

    $this->assertDatabaseHas('positions', [
        'id' => $position->id,
        'deleted_at' => null,
    ]);
});

it('impide eliminar un cargo con personal asociado', function () {
    $position = Position::create([
        'name' => 'Cargo con Personal',
        'description' => null,
        'active' => true,
    ]);

    $organizationalUnit = OrganizationalUnit::create([
        'name' => 'Unidad Position Test',
        'code' => 'POSITION_TEST_UNIT',
        'active' => true,
    ]);

    $profession = Profession::create([
        'name' => 'Profesión Position Test',
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
            "/api/admin/positions/{$position->id}"
        )
        ->assertStatus(409)
        ->assertJsonPath(
            'message',
            'El cargo tiene personal asociado. Desactívelo en lugar de eliminarlo.'
        );

    $this->assertDatabaseHas('positions', [
        'id' => $position->id,
        'deleted_at' => null,
    ]);
});
