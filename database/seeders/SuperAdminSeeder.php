<?php

namespace Database\Seeders;

use App\Enums\Auth\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('simred.super_admin.email');
        $password = config('simred.super_admin.password');

        if (
            ! is_string($email)
            || trim($email) === ''
            || ! is_string($password)
            || $password === ''
        ) {
            throw new RuntimeException(
                'Las credenciales del super administrador no están configuradas.'
            );
        }

        $email = Str::lower(trim($email));

        DB::transaction(function () use (
            $email,
            $password
        ): void {
            $role = Role::firstOrCreate([
                'name' => RoleName::SUPER_ADMIN->value,
                'guard_name' => 'api',
            ]);

            $user = User::withTrashed()
                ->where('email', $email)
                ->first();

            if ($user === null) {
                $user = User::create([
                    'email' => $email,
                    'password' => $password,
                ]);
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }

                if (
                    ! Hash::check(
                        $password,
                        $user->password
                    )
                ) {
                    $user->password = $password;
                    $user->save();
                }
            }

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        });
    }
}
