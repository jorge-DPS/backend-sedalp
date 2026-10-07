<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TerritoryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            PermissionName::REGIONS_VIEW,
            PermissionName::REGIONS_CREATE,
            PermissionName::REGIONS_UPDATE,
            PermissionName::REGIONS_DELETE,
            PermissionName::PROVINCES_VIEW,
            PermissionName::PROVINCES_CREATE,
            PermissionName::PROVINCES_UPDATE,
            PermissionName::PROVINCES_DELETE,
            PermissionName::MUNICIPALITIES_VIEW,
            PermissionName::MUNICIPALITIES_CREATE,
            PermissionName::MUNICIPALITIES_UPDATE,
            PermissionName::MUNICIPALITIES_DELETE,
        ] as $permission) {
            Permission::findOrCreate($permission->value, 'api');
        }

        foreach ([RoleName::DIRECTOR, RoleName::RESPONSABLE, RoleName::TECNICO] as $role) {
            Role::findOrCreate($role->value, 'api')->givePermissionTo([
                PermissionName::REGIONS_VIEW->value,
                PermissionName::PROVINCES_VIEW->value,
                PermissionName::MUNICIPALITIES_VIEW->value,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
