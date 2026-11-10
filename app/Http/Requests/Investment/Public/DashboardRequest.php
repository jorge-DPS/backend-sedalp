<?php

namespace App\Http\Requests\Investment\Public;

class DashboardRequest extends IndexProjectRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['sort_by'], $rules['sort_direction']);

        return $rules;
    }
}
