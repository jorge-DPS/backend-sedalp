<?php

namespace App\Http\Resources\Municipal;

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
            'municipal_term_id' => $this->municipal_term_id,
            'full_name' => $this->full_name,
            'position_title' => $this->position_title,
            'is_current' => $this->is_current,
            'is_published' => $this->is_published,
            'source_reference' => $this->source_reference,
            'notes' => $this->notes,
            'role' => $this->role->value,
            'term_start' => $this->term_start?->toDateString(),
            'term_end' => $this->term_end?->toDateString(),
            'has_photo' => filled($this->photo_path),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
