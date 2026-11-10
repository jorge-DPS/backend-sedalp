<?php

use App\Enums\Auth\RoleName;
use Database\Seeders\MunicipalPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('añade permisos municipales idempotentes sin revocar ni ampliar permisos de inversiones', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Permission::findOrCreate('manual.custom', 'api');
    $previous = [];
    foreach (RoleName::cases() as $name) {
        $role = Role::findByName($name->value, 'api');
        $role->givePermissionTo('manual.custom');
        $previous[$name->value] = $role->permissions()->where('name', 'like', 'investment.%')->pluck('name')->sort()->values()->all();
    }
    $this->seed(MunicipalPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::where('name', 'like', 'municipal.%')->where('guard_name', 'api')->count())->toBe(11);
    foreach (RoleName::cases() as $name) {
        $role = Role::findByName($name->value, 'api');
        expect($role->hasPermissionTo('manual.custom'))->toBeTrue()
            ->and($role->permissions()->where('name', 'like', 'investment.%')->pluck('name')->sort()->values()->all())->toBe($previous[$name->value]);
    }
    foreach (['director' => 3, 'responsable' => 5, 'tecnico' => 9, 'comunicador' => 0] as $name => $count) {
        expect(Role::findByName($name, 'api')->permissions()->where('name', 'like', 'municipal.%')->count())->toBe($count);
    }
    expect(Role::findByName('tecnico', 'api')->hasPermissionTo('municipal.documents.publish'))->toBeFalse()
        ->and(Role::findByName('responsable', 'api')->hasPermissionTo('municipal.documents.publish'))->toBeTrue();
});
