<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
  public function run(): void
  {
    $email = config('simred.super_admin.email');
    $password = config('simred.super_admin.password');

    if (!$email || !$password) {
      throw new RuntimeException(
        'Las credenciales del super administrador no están configuradas.'
      );
    }

    $role = Role::firstOrCreate([
      'name' => 'super_admin',
      'guard_name' => 'api',
    ]);

    $user = User::firstOrCreate(
      [
        'email' => $email,
      ],
      [
        'password' => $password,
      ]
    );

    if (!$user->hasRole($role)) {
      $user->assignRole($role);
    }
  }
}
