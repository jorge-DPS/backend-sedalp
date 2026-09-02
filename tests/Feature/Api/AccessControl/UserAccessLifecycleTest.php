<?php

use App\Enums\Audit\AccessStateAction;
use App\Models\Audit\AccessStateChange;
use App\Models\People\OrganizationalUnit;
use App\Models\People\Position;
use App\Models\People\Profession;
use App\Models\People\StaffMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create([
        'email' => 'lifecycle.admin@test.com',
    ]);

    $this->superAdmin->assignRole('super_admin');
});

function createStaffForLifecycleTest(
    string $suffix = '01',
    bool $active = true
): StaffMember {
    $unit = OrganizationalUnit::create([
        'name' => "Unidad Lifecycle {$suffix}",
        'code' => "LIFECYCLE_{$suffix}",
        'active' => true,
    ]);

    $position = Position::create([
        'name' => "Cargo Lifecycle {$suffix}",
        'active' => true,
    ]);

    $profession = Profession::create([
        'name' => "Profesión Lifecycle {$suffix}",
        'active' => true,
    ]);

    return StaffMember::create([
        'first_names' => 'Personal Lifecycle',
        'paternal_surname' => 'Prueba',
        'ci' => "700000{$suffix}",
        'organizational_unit_id' => $unit->id,
        'position_id' => $position->id,
        'profession_id' => $profession->id,
        'active' => $active,
    ]);
}

it('expone el estado de cuenta y el acceso efectivo', function () {
    $staffMember = createStaffForLifecycleTest();

    $user = User::factory()->create([
        'staff_member_id' => $staffMember->id,
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->getJson("/api/admin/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.account_status', 'active')
        ->assertJsonPath('data.effective_status', 'active')
        ->assertJsonPath('data.can_access', true)
        ->assertJsonPath('data.staff_member.active', true);
});

it('suspende reactiva audita y revoca sesiones anteriores', function () {
    $user = User::factory()->create([
        'email' => 'suspendido.lifecycle@test.com',
        'password' => 'Password123!',
    ]);

    $oldToken = auth('api')->login($user);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' => 'suspended',
                'reason' => 'Suspensión administrativa temporal.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.account_status', 'suspended')
        ->assertJsonPath('data.effective_status', 'suspended')
        ->assertJsonPath('data.can_access', false);

    $this->assertDatabaseHas('access_state_changes', [
        'target_user_id' => $user->id,
        'action' => AccessStateAction::USER_SUSPENDED->value,
        'reason' => 'Suspensión administrativa temporal.',
    ]);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->withToken($oldToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'La sesión fue revocada.',
        ]);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->withToken($oldToken)
        ->postJson('/api/auth/refresh')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'La sesión fue revocada.',
        ]);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this->withHeaders([
        'Authorization' => '',
    ]);

    $this
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])
        ->assertForbidden();

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' => 'active',
                'reason' => 'Fin de la suspensión temporal.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.can_access', true);

    expect(
        AccessStateChange::where(
            'target_user_id',
            $user->id
        )->count()
    )->toBe(2);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])
        ->assertOk();
});

it('impide que un usuario suspenda su propia cuenta', function () {
    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$this->superAdmin->id}/status",
            [
                'status' => 'suspended',
                'reason' => 'Intento de autosuspensión.',
            ]
        )
        ->assertUnprocessable();
});

it('revoca sesiones cuando se actualiza la contraseña', function () {
    $user = User::factory()->create([
        'password' => 'Password123!',
    ]);

    $oldToken = auth('api')->login($user);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$user->id}",
            [
                'password' => 'NuevaPassword456!',
                'password_confirmation' => 'NuevaPassword456!',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('access_state_changes', [
        'target_user_id' => $user->id,
        'action' => AccessStateAction::USER_CREDENTIALS_UPDATED->value,
    ]);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->withToken($oldToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJson([
            'message' => 'La sesión fue revocada.',
        ]);
});

it('impide activar una cuenta mientras su personal está inactivo', function () {
    $staffMember = createStaffForLifecycleTest(
        suffix: '04',
        active: false,
    );

    $user = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'account_status' => 'suspended',
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' => 'active',
                'reason' => 'Intento de reactivación.',
            ]
        )
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Debe reactivar primero al personal asociado.',
        ]);
});

it('elimina lógicamente lista en papelera y restaura con auditoría', function () {
    $staffMember = createStaffForLifecycleTest('02');

    $user = User::factory()->create([
        'staff_member_id' => $staffMember->id,
        'email' => 'restaurar.lifecycle@test.com',
        'password' => 'Password123!',
    ]);

    $oldToken = auth('api')->login($user);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->deleteJson(
            "/api/admin/users/{$user->id}",
            [
                'reason' => 'Baja administrativa.',
            ]
        )
        ->assertNoContent();

    $this->assertSoftDeleted('users', [
        'id' => $user->id,
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->getJson('/api/admin/users/trash')
        ->assertOk()
        ->assertJsonPath('data.0.id', $user->id)
        ->assertJsonPath('data.0.effective_status', 'deleted');

    $this
        ->actingAs($this->superAdmin, 'api')
        ->postJson(
            "/api/admin/users/{$user->id}/restore",
            [
                'reason' => 'Reincorporación autorizada.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.effective_status', 'active');

    $this->assertNotSoftDeleted('users', [
        'id' => $user->id,
    ]);

    expect(
        AccessStateChange::where(
            'target_user_id',
            $user->id
        )->pluck('action')
            ->map->value
            ->all()
    )->toBe([
        AccessStateAction::USER_DELETED->value,
        AccessStateAction::USER_RESTORED->value,
    ]);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->withToken($oldToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();
});

it('impide restaurar una cuenta cuyo personal sigue inactivo', function () {
    $staffMember = createStaffForLifecycleTest(
        suffix: '03',
        active: false,
    );

    $user = User::factory()->create([
        'staff_member_id' => $staffMember->id,
    ]);

    $user->delete();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->postJson(
            "/api/admin/users/{$user->id}/restore",
            [
                'reason' => 'Intento de restauración.',
            ]
        )
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Debe reactivar primero al personal asociado.',
        ]);
});

it('exige motivo para suspender eliminar y restaurar', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/users/{$user->id}/status",
            [
                'status' => 'suspended',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $this
        ->actingAs($this->superAdmin, 'api')
        ->deleteJson(
            "/api/admin/users/{$user->id}"
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

});
