<?php

namespace App\Http\Resources\Investment;

use App\Models\Investment\InvestmentSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvestmentSource */
class InvestmentSourceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'provided_by_name' => $this->provided_by_name,
            'source_type' => $this->source_type,
            'title' => $this->title,
            'fiscal_year' => $this->fiscal_year,
            'document_reference' => $this->document_reference,
            'version_label' => $this->version_label,
            'source_url' => $this->source_url,
            'notes' => $this->notes,
            'received_at' => $this->received_at?->toDateString(),
            'institution' => new InvestmentCatalogResource($this->whenLoaded('institution')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
