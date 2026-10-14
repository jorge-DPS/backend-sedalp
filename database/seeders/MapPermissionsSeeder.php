<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MapPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([PermissionName::MAPS_VIEW, PermissionName::MAPS_CREATE, PermissionName::MAPS_UPDATE] as $permission) {
            Permission::findOrCreate($permission->value, 'api');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
