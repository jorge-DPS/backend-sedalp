<?php

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\RoleName;
use Database\Seeders\InvestmentPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('añade permisos de inversión de forma idempotente y conserva concesiones previas', function (): void {
    Permission::findOrCreate('manual.custom', 'api');
    foreach (RoleName::cases() as $name) {
        Role::findOrCreate($name->value, 'api')->givePermissionTo('manual.custom');
    }

    $this->seed(InvestmentPermissionsSeeder::class);
    $this->seed(InvestmentPermissionsSeeder::class);

    expect(Permission::where('name', 'like', 'investment.%')->where('guard_name', 'api')->count())->toBe(11);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (RoleName::cases() as $name) {
        expect(Role::findByName($name->value, 'api')->hasPermissionTo('manual.custom'))->toBeTrue();
    }

    foreach ([RoleName::DIRECTOR, RoleName::RESPONSABLE, RoleName::TECNICO] as $name) {
        $role = Role::findByName($name->value, 'api');
        foreach (['investment.catalogs.view', 'investment.projects.view', 'investment.sources.view', 'investment.budgets.view'] as $permission) {
            expect($role->hasPermissionTo($permission))->toBeTrue();
        }
        expect($role->hasPermissionTo(PermissionName::INVESTMENT_PROJECTS_DELETE->value))->toBeFalse();
    }

    $technician = Role::findByName(RoleName::TECNICO->value, 'api');
    foreach (['investment.projects.create', 'investment.projects.update', 'investment.sources.create', 'investment.sources.update', 'investment.budgets.create', 'investment.budgets.revise'] as $permission) {
        expect($technician->hasPermissionTo($permission))->toBeTrue();
        expect(Role::findByName(RoleName::DIRECTOR->value, 'api')->hasPermissionTo($permission))->toBeFalse();
        expect(Role::findByName(RoleName::RESPONSABLE->value, 'api')->hasPermissionTo($permission))->toBeFalse();
        expect(Role::findByName(RoleName::COMUNICADOR->value, 'api')->hasPermissionTo($permission))->toBeFalse();
    }
});
