<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'api',
        ]);

        $user = User::firstOrCreate(
            [
                'email' => 'admin@sedalp.gob.bo',
            ],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('Admin12345'),
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
