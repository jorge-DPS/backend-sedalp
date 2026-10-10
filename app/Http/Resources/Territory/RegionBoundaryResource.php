<?php

namespace App\Http\Resources\Territory;

use App\Models\Territory\RegionBoundary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RegionBoundary */
class RegionBoundaryResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'Feature',
            'id' => $this->id,
            'geometry' => $this->geometry,
            'properties' => [
                'region_id' => $this->region_id,
                'name' => $this->region->name,
                'code' => $this->region->code,
                'updated_at' => $this->updated_at,
            ],
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->header('Content-Type', 'application/geo+json');
    }
}
