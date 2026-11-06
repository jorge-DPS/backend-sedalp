<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;

class UpdateProjectRequest extends StoreProjectRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_PROJECTS_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['institution_id'], 'sometimes');
        array_unshift($rules['name'], 'sometimes');

        return $rules;
    }
}
