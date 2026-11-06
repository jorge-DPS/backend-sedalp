<?php

namespace App\Http\Resources\Investment;

use App\Models\Investment\ProjectBudgetRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectBudgetRevision */
class ProjectBudgetRevisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_budget_id' => $this->project_budget_id,
            'revision_number' => $this->revision_number,
            'previous_amount_bob' => $this->previous_amount_bob,
            'new_amount_bob' => $this->new_amount_bob,
            'previous_investment_source_id' => $this->previous_investment_source_id,
            'investment_source_id' => $this->investment_source_id,
            'previous_source_reference' => $this->previous_source_reference,
            'source_reference' => $this->source_reference,
            'previous_funding_source' => $this->previous_funding_source,
            'new_funding_source' => $this->new_funding_source,
            'reason' => $this->reason,
            'previous_availability_status' => $this->previous_availability_status->value,
            'new_availability_status' => $this->new_availability_status->value,
            'previous_investment_source' => new InvestmentSourceResource($this->whenLoaded('previousInvestmentSource')),
            'investment_source' => new InvestmentSourceResource($this->whenLoaded('investmentSource')),
            'effective_date' => $this->effective_date?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
