<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;

class UpdateMunicipalTermRequest extends StoreMunicipalTermRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['start_year'], 'sometimes');
        array_unshift($rules['end_year'], 'sometimes');

        return $rules;
    }
}
