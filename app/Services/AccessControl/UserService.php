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
    User $user,
    array $data
  ): User {
    $user->update($data);

    return $user->load(self::RELATIONS);
  }

  public function updateRole(
    User $user,
    string $role
  ): User {
    return DB::transaction(function () use ($user, $role) {
      $user->syncRoles([$role]);

      return $user->load(self::RELATIONS);
    });
  }

  public function load(User $user): User
  {
    return $user->load(self::RELATIONS);
  }
}
