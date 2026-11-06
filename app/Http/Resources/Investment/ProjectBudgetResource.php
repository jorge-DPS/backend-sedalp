<?php

namespace App\Http\Resources\Investment;

use App\Models\Investment\ProjectBudget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectBudget */
class ProjectBudgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'fiscal_year' => $this->fiscal_year,
            'amount_bob' => $this->amount_bob,
            'investment_source_id' => $this->investment_source_id,
            'source_reference' => $this->source_reference,
            'funding_source' => $this->funding_source,
            'notes' => $this->notes,
            'availability_status' => $this->availability_status->value,
            'verified_at' => $this->verified_at,
            'investment_source' => new InvestmentSourceResource($this->whenLoaded('investmentSource')),
            'revisions_count' => $this->whenCounted('revisions'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
