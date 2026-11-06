<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_PROJECTS_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'institution_id' => ['sometimes', 'integer', Rule::exists(Institution::class, 'id')->withoutTrashed()],
            'sector_id' => ['sometimes', 'integer', Rule::exists(Sector::class, 'id')->withoutTrashed()],
            'strategic_axis_id' => ['sometimes', 'integer', Rule::exists(StrategicAxis::class, 'id')->withoutTrashed()],
            'intervention_type_id' => ['sometimes', 'integer', Rule::exists(InterventionType::class, 'id')->withoutTrashed()],
            'project_status_id' => ['sometimes', 'integer', Rule::exists(ProjectStatus::class, 'id')->withoutTrashed()],
            'province_id' => ['sometimes', 'integer', Rule::exists(Province::class, 'id')->withoutTrashed()],
            'municipality_id' => ['sometimes', 'integer', Rule::exists(Municipality::class, 'id')->withoutTrashed()],
            'region_id' => ['required_with:region_date', 'integer', Rule::exists(Region::class, 'id')->withoutTrashed()],
            'region_date' => ['sometimes', 'date_format:Y-m-d'],
            'source_year' => ['sometimes', 'integer', 'between:1900,2100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort_by' => ['sometimes', Rule::in(['id', 'name', 'source_code', 'source_year', 'total_cost_bob', 'created_at', 'updated_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
