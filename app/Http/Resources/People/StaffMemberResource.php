<?php

namespace App\Http\Resources\People;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'first_names' => $this->first_names,
            'paternal_surname' => $this->paternal_surname,
            'maternal_surname' => $this->maternal_surname,

            'birth_date' => $this->birth_date?->format('Y-m-d'),

            'ci' => $this->ci,
            'ci_complement' => $this->ci_complement,

            'phone' => $this->phone,
            'email' => $this->email,

            'active' => $this->active,

            'organizational_unit' => $this->whenLoaded(
                'organizationalUnit',
                fn () => $this->organizationalUnit
                    ? [
                        'id' => $this->organizationalUnit->id,
                        'name' => $this->organizationalUnit->name,
                        'code' => $this->organizationalUnit->code,
                    ]
                    : null
            ),

            'position' => $this->whenLoaded(
                'position',
                fn () => $this->position
                    ? [
                        'id' => $this->position->id,
                        'name' => $this->position->name,
                    ]
                    : null
            ),

            'profession' => $this->whenLoaded(
                'profession',
                fn () => $this->profession
                    ? [
                        'id' => $this->profession->id,
                        'name' => $this->profession->name,
                    ]
                    : null
            ),

            'user' => $this->whenLoaded(
                'user',
                fn () => $this->user
                    ? [
                        'id' => $this->user->id,
                        'email' => $this->user->email,
                        'account_status' => $this->user
                            ->account_status
                            ->value,
                        'effective_status' => $this->user
                            ->effectiveAccessStatus()
                            ->value,
                        'can_access' => $this->user
                            ->canAccessApi(),
                    ]
                    : null
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
