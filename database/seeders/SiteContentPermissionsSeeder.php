<?php

namespace Database\Seeders;

use App\Enums\Auth\PermissionName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SiteContentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([PermissionName::CONTENT_VIEW, PermissionName::CONTENT_UPDATE, PermissionName::CONTENT_MEDIA_CREATE, PermissionName::CONTENT_MEDIA_DELETE] as $permission) {
            Permission::findOrCreate($permission->value, 'api');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
