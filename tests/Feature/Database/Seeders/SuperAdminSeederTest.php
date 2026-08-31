<?php

use App\Enums\Auth\RoleName;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config()->set(
        'simred.super_admin.email',
        'superadmin.test@simred.local'
    );

    config()->set(
        'simred.super_admin.password',
        'Password123!'
    );
});

it('crea el superadministrador configurado', function () {
    $this->seed(
        SuperAdminSeeder::class
    );

    $user = User::query()
        ->where(
            'email',
            'superadmin.test@simred.local'
        )
        ->firstOrFail();

    expect(
        Hash::check(
            'Password123!',
            $user->password
        )
    )->toBeTrue();

    expect(
        $user->hasRole(
            RoleName::SUPER_ADMIN->value
        )
    )->toBeTrue();
});

it('normaliza el email del superadministrador', function () {
    config()->set(
        'simred.super_admin.email',
        '  SUPERADMIN.TEST@SIMRED.LOCAL  '
    );

    $this->seed(
        SuperAdminSeeder::class
    );

    $this->assertDatabaseHas('users', [
        'email' => 'superadmin.test@simred.local',
        'deleted_at' => null,
    ]);
});

it('restaura un superadministrador eliminado lógicamente', function () {
    $user = User::factory()->create([
        'email' => 'superadmin.test@simred.local',
        'password' => 'OldPassword123!',
    ]);

    $userId = $user->id;

    $user->delete();

    $this->assertSoftDeleted('users', [
        'id' => $userId,
    ]);

    $this->seed(
        SuperAdminSeeder::class
    );

    $restoredUser = User::withTrashed()
        ->findOrFail($userId);

    expect(
        $restoredUser->trashed()
    )->toBeFalse();

    expect(
        Hash::check(
            'Password123!',
            $restoredUser->password
        )
    )->toBeTrue();

    expect(
        $restoredUser->hasRole(
            RoleName::SUPER_ADMIN->value
        )
    )->toBeTrue();
});

it('actualiza la contraseña de un superadministrador existente', function () {
    $user = User::factory()->create([
        'email' => 'superadmin.test@simred.local',
        'password' => 'OldPassword123!',
    ]);

    $userId = $user->id;

    $this->seed(
        SuperAdminSeeder::class
    );

    $user->refresh();

    expect($user->id)
        ->toBe($userId);

    expect(
        Hash::check(
            'Password123!',
            $user->password
        )
    )->toBeTrue();

    expect(
        Hash::check(
            'OldPassword123!',
            $user->password
        )
    )->toBeFalse();

    expect(
        $user->hasRole(
            RoleName::SUPER_ADMIN->value
        )
    )->toBeTrue();
});

it('es idempotente y no duplica el superadministrador', function () {
    $this->seed(
        SuperAdminSeeder::class
    );

    $firstUser = User::query()
        ->where(
            'email',
            'superadmin.test@simred.local'
        )
        ->firstOrFail();

    $firstUserId = $firstUser->id;
    $firstPasswordHash = $firstUser->password;

    $this->seed(
        SuperAdminSeeder::class
    );

    $secondUser = User::query()
        ->where(
            'email',
            'superadmin.test@simred.local'
        )
        ->firstOrFail();

    expect($secondUser->id)
        ->toBe($firstUserId);

    expect($secondUser->password)
        ->toBe($firstPasswordHash);

    expect(
        User::withTrashed()
            ->where(
                'email',
                'superadmin.test@simred.local'
            )
            ->count()
    )->toBe(1);

    expect(
        $secondUser->hasRole(
            RoleName::SUPER_ADMIN->value
        )
    )->toBeTrue();
});

it('falla si las credenciales del superadministrador no están configuradas', function () {
    config()->set(
        'simred.super_admin.email',
        null
    );

    config()->set(
        'simred.super_admin.password',
        null
    );

    expect(
        fn () => $this->seed(
            SuperAdminSeeder::class
        )
    )->toThrow(
        RuntimeException::class,
        'Las credenciales del super administrador no están configuradas.'
    );
});
