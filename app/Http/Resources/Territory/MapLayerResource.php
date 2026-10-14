<?php

namespace App\Http\Resources\Territory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapLayerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->kind,
            'region_id' => $this->region_id,
            'region' => $this->whenLoaded('region', fn (): array => ['id' => $this->region->id, 'name' => $this->region->name]),
            'current_version' => new MapLayerVersionResource($this->whenLoaded('currentVersion')),
        ];
    }
}
