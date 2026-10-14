<?php

namespace App\Http\Resources\Territory;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RegionBoundaryCollection extends ResourceCollection
{
    public static $wrap = null;

    public $collects = RegionBoundaryResource::class;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $this->collection,
        ];
    }

    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->header('Content-Type', 'application/geo+json');
        $response->header('Cache-Control', 'private, no-cache');
    }
}
