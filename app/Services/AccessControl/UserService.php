<?php

namespace App\Services\AccessControl;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const RELATIONS = [
        'staffMember.organizationalUnit',
        'staffMember.position',
        'staffMember.profession',
        'roles.permissions',
        'permissions',
    ];

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $role = $data['role'];

            $permissions = $data['permissions'] ?? [];

            $user = User::create([
                'staff_member_id' => $data['staff_member_id'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->syncRoles([$role]);

            $user->syncPermissions($permissions);

            return $user->load(self::RELATIONS);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->fill($data);

            $user->save();

            return $user->load(self::RELATIONS);
        });
    }

    public function updateAccess(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->syncRoles([
                $data['role'],
            ]);

            if (array_key_exists('permissions', $data)) {
                $user->syncPermissions(
                    $data['permissions'] ?? []
                );
            }

            return $user->load(self::RELATIONS);
        });
    }

    public function load(User $user): User
    {
        return $user->load(self::RELATIONS);
    }
}
