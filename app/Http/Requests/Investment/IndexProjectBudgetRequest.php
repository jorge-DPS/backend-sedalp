<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class IndexProjectBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_BUDGETS_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
