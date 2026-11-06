<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;

class UpdateInvestmentSourceRequest extends StoreInvestmentSourceRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_SOURCES_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['institution_id', 'source_type', 'title'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
