<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use App\Models\Investment\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInvestmentSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_SOURCES_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'institution_id' => ['sometimes', 'integer', Rule::exists(Institution::class, 'id')->withoutTrashed()],
            'fiscal_year' => ['sometimes', 'integer', 'between:1900,2100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort_by' => ['sometimes', Rule::in(['id', 'title', 'fiscal_year', 'received_at', 'created_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
