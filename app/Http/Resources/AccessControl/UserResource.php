<?php

namespace App\Http\Resources\AccessControl;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'email' => $this->email,

            'staff_member_id' => $this->staff_member_id,

            'staff_member' => $this->when(
                $this->relationLoaded('staffMember'),
                function () {
                    if (! $this->staffMember) {
                        return null;
                    }

                    return [
                        'id' => $this->staffMember->id,
                        'first_names' => $this->staffMember->first_names,

                        'organizational_unit' => $this->staffMember
                            ->organizationalUnit
                            ? [
                                'id' => $this->staffMember
                                    ->organizationalUnit->id,

                                'name' => $this->staffMember
                                    ->organizationalUnit->name,

                                'code' => $this->staffMember
                                    ->organizationalUnit->code,
                            ]
                            : null,

                        'position' => $this->staffMember->position
                            ? [
                                'id' => $this->staffMember->position->id,
                                'name' => $this->staffMember->position->name,
                            ]
                            : null,

                        'profession' => $this->staffMember->profession
                            ? [
                                'id' => $this->staffMember->profession->id,
                                'name' => $this->staffMember->profession->name,
                            ]
                            : null,
                    ];
                }
            ),

            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles
                    ->pluck('name')
                    ->values()
            ),

            'direct_permissions' => $this->whenLoaded(
                'permissions',
                fn () => $this->permissions
                    ->pluck('name')
                    ->sort()
                    ->values()
            ),

            'effective_permissions' => $this->when(
                $this->relationLoaded('roles')
                && $this->relationLoaded('permissions'),

                fn () => $this->roles
                    ->flatMap(
                        fn ($role) => $role->permissions
                    )
                    ->merge($this->permissions)
                    ->pluck('name')
                    ->unique()
                    ->sort()
                    ->values()
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
