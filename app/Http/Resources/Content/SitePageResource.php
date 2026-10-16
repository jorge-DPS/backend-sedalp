<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SitePageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'sections_count' => $this->whenCounted('sections'),
            'sections' => SiteSectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
