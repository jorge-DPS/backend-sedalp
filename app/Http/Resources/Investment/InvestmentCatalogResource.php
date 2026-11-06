<?php

namespace App\Http\Resources\Investment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Model */
class InvestmentCatalogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'code' => $this->whenHas('code'),
            'traffic_light' => $this->whenHas('traffic_light'),
        ];
    }
}
