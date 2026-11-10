<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use App\Enums\Auth\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MunicipalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            if (str_starts_with($permission->value, 'municipal.')) {
                Permission::findOrCreate($permission->value, 'api');
            }
        }

        $read = [
            PermissionName::MUNICIPAL_POAS_VIEW->value,
            PermissionName::MUNICIPAL_DOCUMENTS_VIEW->value,
            PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value,
        ];
        foreach ([RoleName::DIRECTOR, RoleName::RESPONSABLE, RoleName::TECNICO] as $name) {
            Role::findOrCreate($name->value, 'api')->givePermissionTo($read);
        }

        Role::findOrCreate(RoleName::TECNICO->value, 'api')->givePermissionTo([
            PermissionName::MUNICIPAL_POAS_CREATE->value,
            PermissionName::MUNICIPAL_POAS_UPDATE->value,
            PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value,
            PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value,
            PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value,
            PermissionName::MUNICIPAL_AUTHORITY_PHOTOS_UPDATE->value,
        ]);
        Role::findOrCreate(RoleName::RESPONSABLE->value, 'api')->givePermissionTo([
            PermissionName::MUNICIPAL_DOCUMENTS_PUBLISH->value,
            PermissionName::MUNICIPAL_AUTHORITIES_PUBLISH->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
