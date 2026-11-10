<?php

namespace App\Http\Resources\Municipal\Public;

use App\Models\Municipal\MunicipalPoa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalPoa */
class MunicipalPoaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipality_id' => $this->municipality_id,
            'fiscal_year' => $this->fiscal_year,
            'title' => $this->title,
            'published_documents_count' => $this->whenHas('published_documents_count'),
        ];
    }
}
