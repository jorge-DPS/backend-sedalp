<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NewsPermissionsSeeder extends Seeder
{
    private const GUARD = 'api';

    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        /*
         * Permisos operativos del comunicador.
         */
        $communicatorPermissionNames = [
            'news.view',
            'news.create',
            'news.update',
            'news.delete',
            'news.publish',
        ];

        /*
         * Permisos administrativos / sistemas.
         *
         * Se crean, pero NO se asignan al comunicador.
         */
        $systemPermissionNames = [
            'news.trash.view',
            'news.restore',
            'news.force_delete',
        ];

        $communicatorPermissions = collect(
            $communicatorPermissionNames
        )->map(
            fn (string $name) => Permission::findOrCreate(
                $name,
                self::GUARD
            )
        );

        foreach ($systemPermissionNames as $name) {
            Permission::findOrCreate(
                $name,
                self::GUARD
            );
        }

        $communicator = Role::findOrCreate(
            'comunicador',
            self::GUARD
        );

        $communicator->syncPermissions(
            $communicatorPermissions
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}