<?php

use App\Enums\Audit\AccessStateAction;
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
        'email' => 'staff.status.admin@test.com',
    ]);

    $this->superAdmin->assignRole('super_admin');

    $unit = OrganizationalUnit::create([
        'name' => 'Unidad Staff Status',
        'code' => 'STAFF_STATUS',
        'active' => true,
    ]);

    $position = Position::create([
        'name' => 'Cargo Staff Status',
        'active' => true,
    ]);

    $profession = Profession::create([
        'name' => 'Profesión Staff Status',
        'active' => true,
    ]);

    $this->staffMember = StaffMember::create([
        'first_names' => 'Personal Estado',
        'paternal_surname' => 'Prueba',
        'ci' => '60000001',
        'organizational_unit_id' => $unit->id,
        'position_id' => $position->id,
        'profession_id' => $profession->id,
        'active' => true,
    ]);

    $this->targetUser = User::factory()->create([
        'staff_member_id' => $this->staffMember->id,
        'email' => 'staff.status.target@test.com',
        'password' => 'Password123!',
    ]);
});

it('desactiva y reactiva personal revocando las sesiones relacionadas', function () {
    $oldToken = auth('api')->login(
        $this->targetUser
    );

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this->withHeaders([
        'Authorization' => '',
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$this->staffMember->id}/status",
            [
                'active' => false,
                'reason' => 'Conclusión temporal de funciones.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.active', false)
        ->assertJsonPath(
            'data.user.effective_status',
            'disabled_by_staff'
        )
        ->assertJsonPath('data.user.can_access', false);

    $this->assertDatabaseHas('access_state_changes', [
        'target_user_id' => $this->targetUser->id,
        'staff_member_id' => $this->staffMember->id,
        'action' => AccessStateAction::STAFF_DEACTIVATED->value,
        'reason' => 'Conclusión temporal de funciones.',
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

    $this->withHeaders([
        'Authorization' => '',
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$this->staffMember->id}/status",
            [
                'active' => true,
                'reason' => 'Reincorporación del personal.',
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.user.can_access', true);

    auth('api')->unsetToken();
    Auth::forgetGuards();

    $this
        ->postJson('/api/auth/login', [
            'email' => $this->targetUser->email,
            'password' => 'Password123!',
        ])
        ->assertOk();
});

it('obliga a usar el endpoint de estado para cambiar active', function () {
    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$this->staffMember->id}",
            [
                'active' => false,
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('active');
});

it('exige motivo al cambiar el estado del personal', function () {
    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$this->staffMember->id}/status",
            [
                'active' => false,
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('impide que un usuario desactive su propio registro de personal', function () {
    $this->targetUser->update([
        'staff_member_id' => null,
    ]);

    $this->superAdmin->update([
        'staff_member_id' => $this->staffMember->id,
    ]);

    $this
        ->actingAs($this->superAdmin, 'api')
        ->patchJson(
            "/api/admin/staff-members/{$this->staffMember->id}/status",
            [
                'active' => false,
                'reason' => 'Intento de baja propia.',
            ]
        )
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'No puede desactivar su propio registro de personal.',
        ]);
});

it('impide eliminar personal aunque su usuario esté eliminado lógicamente', function () {
    $this->targetUser->delete();

    $this
        ->actingAs($this->superAdmin, 'api')
        ->deleteJson(
            "/api/admin/staff-members/{$this->staffMember->id}"
        )
        ->assertConflict();

});
