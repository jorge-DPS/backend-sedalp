<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_PROJECTS_CREATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'integer', Rule::exists(Institution::class, 'id')->withoutTrashed()->where('active', true)],
            'sector_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Sector::class, 'id')->withoutTrashed()->where('active', true)],
            'strategic_axis_id' => ['sometimes', 'nullable', 'integer', Rule::exists(StrategicAxis::class, 'id')->withoutTrashed()->where('active', true)],
            'intervention_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists(InterventionType::class, 'id')->withoutTrashed()->where('active', true)],
            'project_status_id' => ['sometimes', 'nullable', 'integer', Rule::exists(ProjectStatus::class, 'id')->withoutTrashed()->where('active', true)],
            'main_province_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Province::class, 'id')->withoutTrashed()->where('active', true)],
            'primary_investment_source_id' => ['sometimes', 'nullable', 'integer', Rule::exists(InvestmentSource::class, 'id')],
            'name' => ['required', 'string'],
            'source_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:500'],
            'executing_unit' => ['sometimes', 'nullable', 'string', 'max:200'],
            'cycle_phase' => ['sometimes', 'nullable', 'string', 'max:50'],
            'project_condition' => ['sometimes', 'nullable', Rule::in([Project::CONDITION_NEW, Project::CONDITION_CONTINUING])],
            'condition_origin' => ['sometimes', 'nullable', Rule::in([Project::CONDITION_ORIGIN_DECLARED, Project::CONDITION_ORIGIN_INFERRED])],
            'original_status' => ['sometimes', 'nullable', 'string'],
            'territorial_scope' => ['sometimes', 'nullable', 'string', 'max:100'],
            'total_cost_bob' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{1,16}(?:\\.[0-9]{1,2})?$/D'],
            'funding_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_reference' => ['sometimes', 'nullable', 'string'],
            'source_year' => ['sometimes', 'nullable', 'integer', 'between:1900,2100'],
            'province_ids' => ['sometimes', 'array', 'list', 'max:1000'],
            'province_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Province::class, 'id')->withoutTrashed()->where('active', true)],
            'municipality_ids' => ['sometimes', 'array', 'list', 'max:1000'],
            'municipality_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Municipality::class, 'id')->withoutTrashed()->where('active', true)],
            'budgets' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_int($this->input('total_cost_bob'))) {
            $this->merge(['total_cost_bob' => (string) $this->input('total_cost_bob')]);
        }
    }
}
