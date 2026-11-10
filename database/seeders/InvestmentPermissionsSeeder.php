<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InvestmentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            if (str_starts_with($permission->value, 'investment.')) {
                Permission::findOrCreate($permission->value, 'api');
            }
        }

        $read = [
            PermissionName::INVESTMENT_CATALOGS_VIEW->value,
            PermissionName::INVESTMENT_PROJECTS_VIEW->value,
            PermissionName::INVESTMENT_SOURCES_VIEW->value,
            PermissionName::INVESTMENT_BUDGETS_VIEW->value,
        ];

        foreach ([RoleName::DIRECTOR, RoleName::RESPONSABLE, RoleName::TECNICO] as $role) {
            Role::findOrCreate($role->value, 'api')->givePermissionTo($read);
        }

        Role::findOrCreate(RoleName::TECNICO->value, 'api')->givePermissionTo([
            PermissionName::INVESTMENT_PROJECTS_CREATE->value,
            PermissionName::INVESTMENT_PROJECTS_UPDATE->value,
            PermissionName::INVESTMENT_SOURCES_CREATE->value,
            PermissionName::INVESTMENT_SOURCES_UPDATE->value,
            PermissionName::INVESTMENT_BUDGETS_CREATE->value,
            PermissionName::INVESTMENT_BUDGETS_REVISE->value,
        ]);

        Role::findOrCreate(RoleName::RESPONSABLE->value, 'api')->givePermissionTo(PermissionName::INVESTMENT_PROJECTS_PUBLISH->value);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
