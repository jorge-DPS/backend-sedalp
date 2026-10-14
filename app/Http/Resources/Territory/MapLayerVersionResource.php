<?php

namespace App\Http\Resources\Territory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapLayerVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'source_filename' => $this->source_filename,
            'summary' => $this->summary,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
