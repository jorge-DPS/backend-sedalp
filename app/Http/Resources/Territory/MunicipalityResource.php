<?php

namespace App\Http\Resources\Territory;

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
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'name' => $this->name,
            'official_code' => $this->official_code,
            'active' => $this->active,
            'region' => new RegionResource($this->whenLoaded('region')),
            'province' => new ProvinceResource($this->whenLoaded('province')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
