<?php

namespace App\Services\AccessControl;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const SUPER_ADMIN = 'super_admin';

    private const RELATIONS = [
        'staffMember.organizationalUnit',
        'staffMember.position',
        'staffMember.profession',
        'roles.permissions',
    ];

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'staff_member_id' => $data['staff_member_id'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([
                $data['role'],
            ]);

            return $user->load(self::RELATIONS);
        });
    }

    public function update(
        User $actor,
        User $user,
        array $data
    ): User {
        $this->ensureCanModifyUser(
            actor: $actor,
            target: $user,
        );

        $user->update($data);

        return $user->load(self::RELATIONS);
    }

    public function updateRole(
        User $actor,
        User $user,
        string $role
    ): User {
        $this->ensureCanChangeRole(
            actor: $actor,
            target: $user,
            newRole: $role,
        );

        return DB::transaction(function () use (
            $user,
            $role
        ) {
            $user->syncRoles([
                $role,
            ]);

            return $user->load(self::RELATIONS);
        });
    }

    public function load(User $user): User
    {
        return $user->load(self::RELATIONS);
    }

    private function ensureCanModifyUser(
        User $actor,
        User $target
    ): void {
        /*
         * Un usuario normal no puede modificar
         * las credenciales de un superadministrador.
         *
         * Un superadministrador sí puede modificar
         * a otro superadministrador o a sí mismo.
         */
        if (
            $target->hasRole(self::SUPER_ADMIN)
            && ! $actor->hasRole(self::SUPER_ADMIN)
        ) {
            abort(
                403,
                'No puede modificar las credenciales de un superadministrador.'
            );
        }
    }

    private function ensureCanChangeRole(
        User $actor,
        User $target,
        string $newRole
    ): void {
        $targetIsSuperAdmin = $target->hasRole(
            self::SUPER_ADMIN
        );

        $actorIsSuperAdmin = $actor->hasRole(
            self::SUPER_ADMIN
        );

        /*
         * Solo un superadministrador puede:
         *
         * - asignar super_admin;
         * - modificar el rol de otro super_admin.
         */
        if (
            (
                $targetIsSuperAdmin
                || $newRole === self::SUPER_ADMIN
            )
            && ! $actorIsSuperAdmin
        ) {
            abort(
                403,
                'Solo un superadministrador puede administrar el rol super_admin.'
            );
        }

        /*
         * Un superadministrador nunca puede
         * quitarse su propio rol privilegiado.
         *
         * Esto evita dejar accidentalmente
         * el sistema sin administración.
         */
        if (
            $actor->is($target)
            && $targetIsSuperAdmin
            && $newRole !== self::SUPER_ADMIN
        ) {
            abort(
                422,
                'No puede quitarse su propio rol de superadministrador.'
            );
        }
    }
}
