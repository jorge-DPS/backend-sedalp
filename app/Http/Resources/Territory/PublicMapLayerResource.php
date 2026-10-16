<?php

namespace App\Http\Resources\Territory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicMapLayerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'category' => $this->category,
            'region' => $this->whenLoaded('region', fn (): array => ['id' => $this->region->id, 'name' => $this->region->name]),
            'published_at' => $this->published_at?->toIso8601String(),
            'version' => $this->publishedVersion->number,
            'feature_count' => $this->publishedVersion->summary['feature_count'] ?? 0,
            'geometry_types' => array_keys($this->publishedVersion->summary['geometry_types'] ?? []),
            'geojson_url' => route('public.map-layers.geojson', ['mapLayer' => $this->id]),
        ];
    }
}
