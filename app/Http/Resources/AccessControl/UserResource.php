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

      'staff_member' => $this->whenLoaded(
        'staffMember',
        function () {
          if (! $this->staffMember) {
            return null;
          }

          return [
            'id' => $this->staffMember->id,

            'first_names' => $this->staffMember->first_names,

            'paternal_surname' =>
            $this->staffMember->paternal_surname,

            'maternal_surname' =>
            $this->staffMember->maternal_surname,

            'organizational_unit' =>
            $this->staffMember->organizationalUnit
              ? [
                'id' =>
                $this->staffMember
                  ->organizationalUnit
                  ->id,

                'name' =>
                $this->staffMember
                  ->organizationalUnit
                  ->name,
              ]
              : null,

            'position' =>
            $this->staffMember->position
              ? [
                'id' =>
                $this->staffMember
                  ->position
                  ->id,

                'name' =>
                $this->staffMember
                  ->position
                  ->name,
              ]
              : null,

            'profession' =>
            $this->staffMember->profession
              ? [
                'id' =>
                $this->staffMember
                  ->profession
                  ->id,

                'name' =>
                $this->staffMember
                  ->profession
                  ->name,
              ]
              : null,
          ];
        }
      ),

      'roles' => $this->whenLoaded(
        'roles',
        fn() => $this->roles
          ->pluck('name')
          ->values()
      ),

      'permissions' => $this->whenLoaded(
        'roles',
        fn() => $this->getAllPermissions()
          ->pluck('name')
          ->sort()
          ->values()
      ),

      'created_at' => $this->created_at,
      'updated_at' => $this->updated_at,
    ];
  }
}
