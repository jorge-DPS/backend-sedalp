<?php

use App\Enums\Auth\RoleName;
use Database\Seeders\TerritoryPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('agrega permisos territoriales de forma idempotente sin revocar permisos existentes', function () {
    Permission::findOrCreate('staff.view', 'api');
    $director = Role::findOrCreate(RoleName::DIRECTOR->value, 'api');
    $director->givePermissionTo('staff.view');

    $this->seed(TerritoryPermissionsSeeder::class);
    $this->seed(TerritoryPermissionsSeeder::class);

    expect(Permission::where('guard_name', 'api')->count())->toBe(13);
    expect($director->fresh()->hasPermissionTo('staff.view', 'api'))->toBeTrue();

    foreach ([RoleName::DIRECTOR, RoleName::RESPONSABLE, RoleName::TECNICO] as $roleName) {
        $role = Role::findByName($roleName->value, 'api');

        foreach (['regions.view', 'provinces.view', 'municipalities.view'] as $permission) {
            expect($role->hasPermissionTo($permission))->toBeTrue();
        }

        expect($role->hasPermissionTo('municipalities.create'))->toBeFalse();
    }
});
