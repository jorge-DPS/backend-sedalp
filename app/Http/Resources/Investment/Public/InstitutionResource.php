<?php

namespace App\Http\Resources\Investment\Public;

use App\Models\Investment\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Institution */
class InstitutionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'projects_count' => $this->whenHas('projects_count'),
        ];
    }
}
