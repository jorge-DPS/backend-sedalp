<?php

use App\Models\People\Position;
use App\Models\People\Profession;
use App\Models\People\StaffMember;
use App\Models\User;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, PositionSeeder::class]);
    $this->operator = User::factory()->create();
    $this->operator->givePermissionTo(['users.view', 'users.create', 'users.update', 'roles.assign', 'staff.create', 'staff.update']);
    $this->payload = ['first_names' => 'Persona', 'ci' => '5551234', 'position_id' => Position::where('name', 'Técnico')->firstOrFail()->id];
});
it('creates staff without optional catalogs and still requires a position', function (): void {
    $this->actingAs($this->operator, 'api')->postJson('/api/admin/staff-members', $this->payload)->assertCreated();
    $staff = StaffMember::where('ci', '5551234')->firstOrFail();
    expect($staff->organizational_unit_id)->toBeNull()->and($staff->profession_id)->toBeNull();
    $this->postJson('/api/admin/staff-members', ['first_names' => 'Otro', 'ci' => '8888'])->assertUnprocessable()->assertJsonValidationErrors('position_id');
});
it('creates a user and linked staff without optional catalogs', function (): void {
    $this->actingAs($this->operator, 'api')->postJson('/api/admin/users', [
        'email' => 'optional@example.test', 'password' => 'StrongPassword!123', 'password_confirmation' => 'StrongPassword!123', 'role' => 'tecnico',
        'staff_member' => $this->payload,
    ])->assertCreated()->assertJsonPath('data.staff_member.profession', null)->assertJsonPath('data.staff_member.organizational_unit', null);
});
it('allows clearing optional catalogs on both update endpoints', function (): void {
    $staff = StaffMember::create($this->payload);
    $user = User::factory()->create(['staff_member_id' => $staff->id]);
    $this->actingAs($this->operator, 'api')->patchJson('/api/admin/users/'.$user->id, [
        'staff_member' => ['organizational_unit_id' => null, 'profession_id' => null],
    ])->assertOk();
    expect($staff->fresh()->profession_id)->toBeNull()->and($staff->fresh()->organizational_unit_id)->toBeNull();
    $this->patchJson('/api/admin/staff-members/'.$staff->id, ['organizational_unit_id' => null, 'profession_id' => null])->assertOk();
    $this->patchJson('/api/admin/staff-members/'.$staff->id, ['position_id' => null])->assertUnprocessable();
});
it('links new staff to an existing account with only required fields', function (): void {
    $user = User::factory()->create(['staff_member_id' => null]);
    $this->actingAs($this->operator, 'api')->patchJson('/api/admin/users/'.$user->id, ['staff_member' => $this->payload])->assertOk();
    expect($user->fresh()->staff_member_id)->not->toBeNull();
});
it('rejects invalid or inactive optional references', function (): void {
    $profession = Profession::create(['name' => 'Profesión inactiva', 'active' => false]);
    $this->actingAs($this->operator, 'api')->postJson('/api/admin/staff-members', [...$this->payload, 'profession_id' => $profession->id, 'organizational_unit_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors(['profession_id', 'organizational_unit_id']);
});
it('provides catalogs with user permissions and no separate catalog permissions', function (): void {
    $this->getJson('/api/admin/users/catalogs')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->getJson('/api/admin/users/catalogs')->assertForbidden();
    Position::create(['name' => 'Inactive', 'active' => false]);
    $this->actingAs($this->operator, 'api')->getJson('/api/admin/users/catalogs')->assertOk()->assertJsonCount(5, 'data.positions')->assertJsonCount(0, 'data.professions')->assertJsonCount(0, 'data.organizational_units')->assertJsonMissing(['name' => 'super_admin'])->assertJsonMissing(['name' => 'Inactive']);
});
it('seeds positions without duplicates or reactivating archived entries', function (): void {
    $position = Position::where('name', 'Director')->firstOrFail();
    $position->update(['active' => false]);
    $position->delete();
    $this->seed(PositionSeeder::class);
    expect(Position::withTrashed()->count())->toBe(5)->and($position->fresh()->active)->toBeFalse();
});
