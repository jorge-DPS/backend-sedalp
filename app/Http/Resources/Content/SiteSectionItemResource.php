<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSectionItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'icon_key' => $this->icon_key,
            'position' => $this->position,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
