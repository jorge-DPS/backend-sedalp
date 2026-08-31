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

    $this->staffRole = Role::create([
        'name' => 'staff_test',
        'guard_name' => 'api',
    ]);

    $this->staffRole->givePermissionTo([
        'staff.view',
        'staff.create',
        'staff.update',
        'staff.delete',
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole($this->staffRole);

    $this->organizationalUnit = OrganizationalUnit::create([
        'name' => 'Unidad de Pruebas',
        'code' => 'TEST_UNIT',
        'description' => 'Unidad utilizada por los tests.',
        'active' => true,
    ]);

    $this->position = Position::create([
        'name' => 'Cargo Test',
        'description' => 'Cargo utilizado en pruebas.',
        'active' => true,
    ]);

    $this->profession = Profession::create([
        'name' => 'Profesión Test',
        'active' => true,
    ]);

    $this->validStaffData = [
        'first_names' => 'Juan Carlos',
        'paternal_surname' => 'Pérez',
        'maternal_surname' => 'Mamani',
        'birth_date' => '1995-05-20',
        'ci' => '12345678',
        'ci_complement' => '1A',
        'phone' => '76543210',
        'email' => 'juan@example.com',
        'organizational_unit_id' => $this->organizationalUnit->id,
        'position_id' => $this->position->id,
        'profession_id' => $this->profession->id,
        'active' => true,
    ];
});

it('rechaza listar personal sin autenticación', function () {
    $this->getJson('/api/admin/staff-members')
        ->assertUnauthorized();
});

it('rechaza crear personal sin permiso', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson(
            '/api/admin/staff-members',
            $this->validStaffData
        )
        ->assertForbidden();
});

it('crea personal con datos válidos', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/staff-members',
            $this->validStaffData
        );

    $response->assertCreated();

    $this->assertDatabaseHas('staff_members', [
        'first_names' => 'Juan Carlos',
        'paternal_surname' => 'Pérez',
        'maternal_surname' => 'Mamani',
        'ci' => '12345678',
        'ci_complement' => '1A',
        'phone' => '76543210',
        'email' => 'juan@example.com',
        'active' => true,
    ]);
});

it('permite crear personal sin apellido paterno', function () {
    $data = $this->validStaffData;
    $data['paternal_surname'] = null;

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertCreated();

    $this->assertDatabaseHas('staff_members', [
        'ci' => '12345678',
        'paternal_surname' => null,
    ]);
});

it('rechaza un CI que contiene letras', function () {
    $data = $this->validStaffData;
    $data['ci'] = '123ABC';

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ci');
});

it('rechaza un CI mayor a 15 dígitos', function () {
    $data = $this->validStaffData;
    $data['ci'] = '1234567890123456';

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ci');
});

it('normaliza el complemento del CI a mayúsculas', function () {
    $data = $this->validStaffData;
    $data['ci_complement'] = '1a';

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertCreated();

    $this->assertDatabaseHas('staff_members', [
        'ci' => '12345678',
        'ci_complement' => '1A',
    ]);
});

it('rechaza complemento de CI con longitud incorrecta', function () {
    $data = $this->validStaffData;
    $data['ci_complement'] = 'ABC';

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ci_complement');
});

it('rechaza una fecha de nacimiento futura', function () {
    $data = $this->validStaffData;
    $data['birth_date'] = now()
        ->addDay()
        ->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('birth_date');
});

it('rechaza una unidad organizacional inactiva', function () {
    $inactiveUnit = OrganizationalUnit::create([
        'name' => 'Unidad Inactiva',
        'code' => 'INACTIVE_TEST',
        'active' => false,
    ]);

    $data = $this->validStaffData;
    $data['organizational_unit_id'] = $inactiveUnit->id;

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('organizational_unit_id');
});

it('impide duplicar CI y complemento', function () {
    StaffMember::create($this->validStaffData);

    $duplicate = $this->validStaffData;
    $duplicate['first_names'] = 'Otra Persona';
    $duplicate['email'] = 'otra@example.com';

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/staff-members', $duplicate)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ci');

    expect(
        StaffMember::withTrashed()
            ->where('ci', '12345678')
            ->where('ci_complement', '1A')
            ->count()
    )->toBe(1);
});

it('actualiza un miembro del personal', function () {
    $staff = StaffMember::create(
        $this->validStaffData
    );

    $response = $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$staff->id}",
            [
                'first_names' => 'Juan Actualizado',
                'phone' => '70000000',
            ]
        );

    $response->assertOk();

    $this->assertDatabaseHas('staff_members', [
        'id' => $staff->id,
        'first_names' => 'Juan Actualizado',
        'phone' => '70000000',
    ]);
});

it('elimina personal mediante soft delete', function () {
    $staff = StaffMember::create(
        $this->validStaffData
    );

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/staff-members/{$staff->id}"
        )
        ->assertNoContent();

    $this->assertSoftDeleted('staff_members', [
        'id' => $staff->id,
    ]);
});

it('rechaza listar personal sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/staff-members')
        ->assertForbidden();
});

it('normaliza y aplica el filtro search al listar personal', function () {
    $matchingStaff = StaffMember::create(
        $this->validStaffData
    );

    $otherData = $this->validStaffData;
    $otherData['first_names'] = 'María Elena';
    $otherData['paternal_surname'] = 'Quispe';
    $otherData['maternal_surname'] = 'Flores';
    $otherData['ci'] = '87654321';
    $otherData['ci_complement'] = null;
    $otherData['email'] = 'maria@example.com';

    $otherStaff = StaffMember::create(
        $otherData
    );

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?search=%20%20Juan%20%20'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($matchingStaff->id)
        ->not->toContain($otherStaff->id);
});

it('filtra personal por unidad organizacional cargo y profesión', function () {
    $matchingStaff = StaffMember::create(
        $this->validStaffData
    );

    $otherUnit = OrganizationalUnit::create([
        'name' => 'Otra Unidad',
        'code' => 'OTHER_UNIT',
        'active' => true,
    ]);

    $otherPosition = Position::create([
        'name' => 'Otro Cargo',
        'active' => true,
    ]);

    $otherProfession = Profession::create([
        'name' => 'Otra Profesión',
        'active' => true,
    ]);

    $otherData = $this->validStaffData;
    $otherData['first_names'] = 'María';
    $otherData['ci'] = '87654321';
    $otherData['ci_complement'] = null;
    $otherData['email'] = 'maria@example.com';
    $otherData['organizational_unit_id'] = $otherUnit->id;
    $otherData['position_id'] = $otherPosition->id;
    $otherData['profession_id'] = $otherProfession->id;

    $otherStaff = StaffMember::create(
        $otherData
    );

    foreach ([
        'organizational_unit_id' => $this->organizationalUnit->id,
        'position_id' => $this->position->id,
        'profession_id' => $this->profession->id,
    ] as $filter => $value) {
        $response = $this
            ->actingAs($this->user, 'api')
            ->getJson(
                "/api/admin/staff-members?{$filter}={$value}"
            )
            ->assertOk();

        $ids = collect(
            $response->json('data')
        )->pluck('id');

        expect($ids)
            ->toContain($matchingStaff->id)
            ->not->toContain($otherStaff->id);
    }
});

it('permite filtrar personal por un catálogo inactivo asociado', function () {
    $staff = StaffMember::create(
        $this->validStaffData
    );

    $this->position->update([
        'active' => false,
    ]);

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            "/api/admin/staff-members?position_id={$this->position->id}"
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($staff->id);
});

it('filtra personal inactivo', function () {
    $activeStaff = StaffMember::create(
        $this->validStaffData
    );

    $inactiveData = $this->validStaffData;
    $inactiveData['first_names'] = 'Personal Inactivo';
    $inactiveData['ci'] = '87654321';
    $inactiveData['ci_complement'] = null;
    $inactiveData['email'] = 'inactivo@example.com';
    $inactiveData['active'] = false;

    $inactiveStaff = StaffMember::create(
        $inactiveData
    );

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?active=false'
        )
        ->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($inactiveStaff->id)
        ->not->toContain($activeStaff->id);
});

it('rechaza filtros inválidos al listar personal', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?active=invalido'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'active'
        );

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?organizational_unit_id=999999999'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'organizational_unit_id'
        );

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?position_id=999999999'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'position_id'
        );

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?profession_id=999999999'
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'profession_id'
        );
});

it('rechaza paginación inválida al listar personal', function () {
    foreach (['abc', '0', '101'] as $perPage) {
        $this
            ->actingAs($this->user, 'api')
            ->getJson(
                "/api/admin/staff-members?per_page={$perPage}"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'per_page'
            );
    }
});

it('acepta el límite máximo de paginación al listar personal', function () {
    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            '/api/admin/staff-members?per_page=100'
        )
        ->assertOk()
        ->assertJsonPath(
            'meta.per_page',
            100
        );
});
