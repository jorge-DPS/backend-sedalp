<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;

class StoreProjectBudgetRevisionRequest extends StoreProjectBudgetRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_BUDGETS_REVISE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['fiscal_year'], $rules['verified_at'], $rules['notes']);

        return [
            ...$rules,
            'effective_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reason' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
