<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteSectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type,
            'position' => $this->position,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'image_alt' => $this->image_alt,
            'desktop_media_id' => $this->desktop_media_id,
            'mobile_media_id' => $this->mobile_media_id,
            'desktop_media' => new SiteMediaResource($this->whenLoaded('desktopMedia')),
            'mobile_media' => new SiteMediaResource($this->whenLoaded('mobileMedia')),
            'options' => (object) $this->options,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toISOString(),
            'items' => SiteSectionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
