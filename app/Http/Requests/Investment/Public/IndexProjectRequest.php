<?php

namespace App\Http\Requests\Investment\Public;

use App\Http\Requests\Investment\IndexProjectRequest as AdminIndexProjectRequest;
use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Region;
use Illuminate\Validation\Rule;

class IndexProjectRequest extends AdminIndexProjectRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['per_page'] = ['sometimes', 'integer', 'between:1,50'];
        $rules['fiscal_year'] = ['sometimes', 'integer', 'between:1900,2100'];
        $rules['region_id'] = ['sometimes', 'integer', Rule::exists(Region::class, 'id')->withoutTrashed()];
        foreach ([
            'institution_id' => Institution::class, 'sector_id' => Sector::class,
            'strategic_axis_id' => StrategicAxis::class, 'intervention_type_id' => InterventionType::class,
            'project_status_id' => ProjectStatus::class,
        ] as $field => $model) {
            $rules[$field] = ['sometimes', 'integer', Rule::exists($model, 'id')];
        }

        return $rules;
    }
}
