<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'api';

    public function run(): void
    {
        // Limpiar caché antes de trabajar
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Crear primero TODOS los permisos
        $this->createPermissions();

        // 2. Crear los roles
        $this->createRoles();

        // Limpiar caché después de crear permisos/roles
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 3. Recién ahora asignar permisos
        $this->assignPermissionsToRoles();

        // Limpiar nuevamente
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate(
                $permission->value,
                self::GUARD
            );
        }
    }

    private function createRoles(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate(
                $role->value,
                self::GUARD
            );
        }
    }

    private function assignPermissionsToRoles(): void
    {
        $director = Role::findByName(
            RoleName::DIRECTOR->value,
            self::GUARD
        );

        $director->syncPermissions([
            PermissionName::STAFF_VIEW->value,

            PermissionName::REGIONS_VIEW->value,
            PermissionName::PROVINCES_VIEW->value,
            PermissionName::MUNICIPALITIES_VIEW->value,

            PermissionName::REGION_ASSIGNMENTS_VIEW->value,
            PermissionName::REGION_ASSIGNMENTS_CREATE->value,
            PermissionName::REGION_ASSIGNMENTS_UPDATE->value,

            PermissionName::TECHNICAL_ASSISTANCES_VIEW->value,
        ]);

        $responsableProgramas = Role::findByName(
            RoleName::RESPONSABLE_PROGRAMAS->value,
            self::GUARD
        );

        $responsableProgramas->syncPermissions([
            PermissionName::REGIONS_VIEW->value,
            PermissionName::PROVINCES_VIEW->value,
            PermissionName::MUNICIPALITIES_VIEW->value,
            PermissionName::TECHNICAL_ASSISTANCES_VIEW->value,
        ]);

        $tecnico = Role::findByName(
            RoleName::TECNICO->value,
            self::GUARD
        );

        $tecnico->syncPermissions([
            PermissionName::REGIONS_VIEW->value,
            PermissionName::PROVINCES_VIEW->value,
            PermissionName::MUNICIPALITIES_VIEW->value,

            PermissionName::REGION_ASSIGNMENTS_VIEW->value,

            PermissionName::TECHNICAL_ASSISTANCES_VIEW->value,
            PermissionName::TECHNICAL_ASSISTANCES_CREATE->value,
            PermissionName::TECHNICAL_ASSISTANCES_UPDATE->value,
        ]);
    }
}
