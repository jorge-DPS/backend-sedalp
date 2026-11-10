<?php

namespace App\Http\Resources\Investment;

use App\Http\Resources\Territory\MunicipalityResource;
use App\Http\Resources\Territory\ProvinceResource;
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
            'institution_id' => $this->institution_id,
            'sector_id' => $this->sector_id,
            'strategic_axis_id' => $this->strategic_axis_id,
            'intervention_type_id' => $this->intervention_type_id,
            'project_status_id' => $this->project_status_id,
            'main_province_id' => $this->main_province_id,
            'primary_investment_source_id' => $this->primary_investment_source_id,
            'source_code' => $this->source_code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'executing_unit' => $this->executing_unit,
            'cycle_phase' => $this->cycle_phase,
            'project_condition' => $this->project_condition,
            'condition_origin' => $this->condition_origin,
            'original_status' => $this->original_status,
            'territorial_scope' => $this->territorial_scope,
            'total_cost_bob' => $this->total_cost_bob,
            'funding_source' => $this->funding_source,
            'source_reference' => $this->source_reference,
            'source_year' => $this->source_year,
            'published_at' => $this->published_at,
            'is_published' => $this->isPubliclyAvailable(),
            'institution' => new InvestmentCatalogResource($this->whenLoaded('institution')),
            'sector' => new InvestmentCatalogResource($this->whenLoaded('sector')),
            'strategic_axis' => new InvestmentCatalogResource($this->whenLoaded('strategicAxis')),
            'intervention_type' => new InvestmentCatalogResource($this->whenLoaded('interventionType')),
            'project_status' => new InvestmentCatalogResource($this->whenLoaded('projectStatus')),
            'main_province' => new ProvinceResource($this->whenLoaded('mainProvince')),
            'primary_investment_source' => new InvestmentSourceResource($this->whenLoaded('primaryInvestmentSource')),
            'provinces' => ProvinceResource::collection($this->whenLoaded('provinces')),
            'municipalities' => MunicipalityResource::collection($this->whenLoaded('municipalities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
