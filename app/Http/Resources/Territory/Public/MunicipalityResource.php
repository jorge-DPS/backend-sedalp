<?php

namespace App\Http\Resources\Territory\Public;

use App\Models\Territory\Municipality;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Municipality */
class MunicipalityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'official_code' => $this->official_code,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'region' => new TerritorialCatalogResource($this->whenLoaded('region')),
            'province' => new TerritorialCatalogResource($this->whenLoaded('province')),
        ];
    }
}
