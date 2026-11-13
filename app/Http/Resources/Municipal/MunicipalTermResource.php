<?php

namespace App\Http\Resources\Municipal;

use App\Models\Municipal\MunicipalTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalTerm */
class MunicipalTermResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'municipality_id' => $this->municipality_id,
            'start_year' => $this->start_year, 'end_year' => $this->end_year,
            'label' => 'Gestión '.$this->start_year.'–'.$this->end_year,
            'authorities_count' => $this->whenCounted('municipalAuthorities'),
            'authorities' => MunicipalAuthorityResource::collection($this->whenLoaded('municipalAuthorities')),
        ];
    }
}
