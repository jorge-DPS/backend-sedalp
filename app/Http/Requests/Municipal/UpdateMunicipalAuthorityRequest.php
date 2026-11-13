<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;

class UpdateMunicipalAuthorityRequest extends StoreMunicipalAuthorityRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['full_name'], 'sometimes');
        $rules['role'] = ['missing'];
        array_unshift($rules['municipal_term_id'], 'sometimes');

        return $rules;
    }
}
