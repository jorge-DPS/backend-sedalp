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

    $this->organizationalUnitRole = Role::create([
        'name' => 'organizational_unit_test',
        'guard_name' => 'api',
    ]);

    $this->organizationalUnitRole->givePermissionTo([
        'organizational_units.view',
        'organizational_units.create',
        'organizational_units.update',
        'organizational_units.delete',
    ]);

    $this->user = User::factory()->create();

    $this->user->assignRole(
        $this->organizationalUnitRole
    );
});

it('rechaza listar unidades organizacionales sin autenticación', function () {
    $this
        ->getJson('/api/admin/organizational-units')
        ->assertUnauthorized();
});

it('rechaza listar unidades organizacionales sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/organizational-units')
        ->assertForbidden();
});

it('lista unidades organizacionales con permiso', function () {
    OrganizationalUnit::create([
        'name' => 'Dirección Administrativa',
        'code' => 'DIRECCION_ADMINISTRATIVA',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson('/api/admin/organizational-units')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'code',
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

it('normaliza y aplica el filtro search', function () {
    $matchingUnit = OrganizationalUnit::create([
        'name' => 'Dirección Administrativa',
        'code' => 'DIRECCION_ADMINISTRATIVA',
        'active' => true,
    ]);

    $otherUnit = OrganizationalUnit::create([
        'name' => 'Unidad Técnica',
        'code' => 'UNIDAD_TECNICA',
        'active' => true,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/organizational-units?search=%20%20Administrativa%20%20'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($matchingUnit->id)
        ->not->toContain($otherUnit->id);
});

it('filtra unidades organizacionales inactivas', function () {
    $activeUnit = OrganizationalUnit::create([
        'name' => 'Unidad Activa',
        'code' => 'UNIDAD_ACTIVA',
        'active' => true,
    ]);

    $inactiveUnit = OrganizationalUnit::create([
        'name' => 'Unidad Inactiva',
        'code' => 'UNIDAD_INACTIVA',
        'active' => false,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/organizational-units?active=false'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($inactiveUnit->id)
        ->not->toContain($activeUnit->id);
});

it('rechaza un filtro active inválido', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/organizational-units?active=invalido'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('active');
});

it('rechaza una paginación inválida', function () {
    foreach (['abc', '0', '101'] as $perPage) {
        $this
            ->actingAs($this->user, 'api')
            ->getJson(
                "/api/admin/organizational-units?per_page={$perPage}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
});

it('crea una unidad organizacional normalizando sus datos', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/organizational-units', [
            'name' => '  Dirección Jurídica  ',
            'code' => '  dir_juridica  ',
            'description' => 'Asesoramiento jurídico institucional.',
        ])
        ->assertCreated()
        ->assertJsonPath(
            'data.name',
            'Dirección Jurídica'
        )
        ->assertJsonPath(
            'data.code',
            'DIR_JURIDICA'
        )
        ->assertJsonPath(
            'data.active',
            true
        );

    $this->assertDatabaseHas(
        'organizational_units',
        [
            'id' => $response->json('data.id'),
            'name' => 'Dirección Jurídica',
            'code' => 'DIR_JURIDICA',
            'description' => 'Asesoramiento jurídico institucional.',
            'active' => true,
        ]
    );
});

it('rechaza crear una unidad organizacional sin permiso', function () {
    $role = Role::create([
        'name' => 'organizational_unit_view_only_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'organizational_units.view'
    );

    $user = User::factory()->create();

    $user->assignRole($role);

    $this
        ->actingAs($user, 'api')
        ->postJson('/api/admin/organizational-units', [
            'name' => 'Unidad Restringida',
            'code' => 'UNIDAD_RESTRINGIDA',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing(
        'organizational_units',
        [
            'code' => 'UNIDAD_RESTRINGIDA',
        ]
    );
});

it('rechaza un código inválido al crear una unidad organizacional', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/organizational-units', [
            'name' => 'Unidad Inválida',
            'code' => 'CODIGO INVALIDO',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->assertDatabaseMissing(
        'organizational_units',
        [
            'name' => 'Unidad Inválida',
        ]
    );
});

it('rechaza nombre y código duplicados al crear una unidad organizacional', function () {
    OrganizationalUnit::create([
        'name' => 'Unidad Existente',
        'code' => 'UNIDAD_EXISTENTE',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/organizational-units', [
            'name' => 'Unidad Existente',
            'code' => 'UNIDAD_EXISTENTE',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'code',
        ]);
});

it('muestra una unidad organizacional', function () {
    $unit = OrganizationalUnit::create([
        'name' => 'Dirección Técnica',
        'code' => 'DIRECCION_TECNICA',
        'description' => 'Dirección técnica institucional.',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            "/api/admin/organizational-units/{$unit->id}"
        )
        ->assertOk()
        ->assertJsonPath(
            'data.id',
            $unit->id
        )
        ->assertJsonPath(
            'data.name',
            'Dirección Técnica'
        )
        ->assertJsonPath(
            'data.code',
            'DIRECCION_TECNICA'
        );
});

it('actualiza una unidad organizacional normalizando sus datos', function () {
    $unit = OrganizationalUnit::create([
        'name' => 'Unidad Original',
        'code' => 'UNIDAD_ORIGINAL',
        'description' => null,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/organizational-units/{$unit->id}",
            [
                'name' => '  Unidad Actualizada  ',
                'code' => '  unidad_actualizada  ',
                'description' => 'Descripción actualizada.',
                'active' => false,
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'data.name',
            'Unidad Actualizada'
        )
        ->assertJsonPath(
            'data.code',
            'UNIDAD_ACTUALIZADA'
        )
        ->assertJsonPath(
            'data.active',
            false
        );

    $this->assertDatabaseHas(
        'organizational_units',
        [
            'id' => $unit->id,
            'name' => 'Unidad Actualizada',
            'code' => 'UNIDAD_ACTUALIZADA',
            'description' => 'Descripción actualizada.',
            'active' => false,
        ]
    );
});

it('rechaza valores duplicados al actualizar una unidad organizacional', function () {
    $firstUnit = OrganizationalUnit::create([
        'name' => 'Primera Unidad',
        'code' => 'PRIMERA_UNIDAD',
        'active' => true,
    ]);

    OrganizationalUnit::create([
        'name' => 'Segunda Unidad',
        'code' => 'SEGUNDA_UNIDAD',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/organizational-units/{$firstUnit->id}",
            [
                'name' => 'Segunda Unidad',
                'code' => 'SEGUNDA_UNIDAD',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'code',
        ]);

    $this->assertDatabaseHas(
        'organizational_units',
        [
            'id' => $firstUnit->id,
            'name' => 'Primera Unidad',
            'code' => 'PRIMERA_UNIDAD',
        ]
    );
});

it('elimina una unidad organizacional mediante soft delete', function () {
    $unit = OrganizationalUnit::create([
        'name' => 'Unidad Eliminable',
        'code' => 'UNIDAD_ELIMINABLE',
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/organizational-units/{$unit->id}"
        )
        ->assertNoContent();

    $this->assertSoftDeleted(
        'organizational_units',
        [
            'id' => $unit->id,
        ]
    );
});

it('impide eliminar una unidad organizacional con personal asociado', function () {
    $unit = OrganizationalUnit::create([
        'name' => 'Unidad con Personal',
        'code' => 'UNIDAD_CON_PERSONAL',
        'active' => true,
    ]);

    $position = Position::create([
        'name' => 'Cargo Unidad Test',
        'active' => true,
    ]);

    $profession = Profession::create([
        'name' => 'Profesión Unidad Test',
        'active' => true,
    ]);

    StaffMember::create([
        'first_names' => 'Juan',
        'paternal_surname' => 'Pérez',
        'maternal_surname' => 'Mamani',
        'ci' => '987654321',
        'organizational_unit_id' => $unit->id,
        'position_id' => $position->id,
        'profession_id' => $profession->id,
        'active' => true,
    ]);

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/organizational-units/{$unit->id}"
        )
        ->assertStatus(409)
        ->assertJsonPath(
            'message',
            'La unidad organizacional tiene personal asociado. Desactívela en lugar de eliminarla.'
        );

    $this->assertDatabaseHas(
        'organizational_units',
        [
            'id' => $unit->id,
            'deleted_at' => null,
        ]
    );
});
