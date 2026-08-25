<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NewsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $permissionNames = [
            'news.view',
            'news.create',
            'news.update',
            'news.delete',
            'news.publish',
        ];

        $permissions = collect($permissionNames)
            ->map(function (string $name) {
                return Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'api',
                ]);
            });

        $communicator = Role::firstOrCreate([
            'name' => 'comunicador',
            'guard_name' => 'api',
        ]);

        $communicator->syncPermissions($permissions);

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
