<?php

namespace App\Http\Resources\Investment\Public;

use App\Http\Resources\Territory\Public\MunicipalityResource;
use App\Http\Resources\Territory\Public\TerritorialCatalogResource;
use App\Models\Investment\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_code' => $this->source_code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'executing_unit' => $this->executing_unit,
            'source_year' => $this->source_year,
            'total_cost_bob' => $this->total_cost_bob,
            'project_condition' => $this->project_condition,
            'condition_origin' => $this->condition_origin,
            'territorial_scope' => $this->territorial_scope,
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'sector' => $this->whenLoaded('sector', fn (): array => $this->sector->only(['id', 'name'])),
            'strategic_axis' => $this->whenLoaded('strategicAxis', fn (): array => $this->strategicAxis->only(['id', 'name'])),
            'intervention_type' => $this->whenLoaded('interventionType', fn (): array => $this->interventionType->only(['id', 'name'])),
            'project_status' => $this->whenLoaded('projectStatus', fn (): array => $this->projectStatus->only(['id', 'name', 'code', 'traffic_light'])),
            'main_province' => new TerritorialCatalogResource($this->whenLoaded('mainProvince')),
            'provinces' => TerritorialCatalogResource::collection($this->whenLoaded('provinces')),
            'municipalities' => MunicipalityResource::collection($this->whenLoaded('municipalities')),
            'annual_budget' => $this->relationLoaded('budgets') ? [
                'fiscal_year' => (int) $request->query('fiscal_year'),
                'recorded' => $this->budgets->isNotEmpty(),
                'availability_status' => $this->budgets->first()?->availability_status->value,
                'amount_bob' => $this->budgets->first()?->amount_bob,
            ] : null,
        ];
    }
}
