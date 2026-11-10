<?php

namespace App\Http\Resources\Municipal\Public;

use App\Models\Municipal\MunicipalAuthority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalAuthority */
class MunicipalAuthorityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipality_id' => $this->municipality_id,
            'full_name' => $this->full_name,
            'position_title' => $this->position_title,
            'is_current' => $this->is_current,
            'role' => $this->role->value,
            'term_start' => $this->term_start?->toDateString(),
            'term_end' => $this->term_end?->toDateString(),
            'has_photo' => filled($this->photo_path),
            'photo_url' => filled($this->photo_path) ? route('public.municipal.authorities.photo', [
                'municipality' => $this->municipality_id, 'municipalAuthority' => $this->id,
            ], false) : null,
        ];
    }
}
