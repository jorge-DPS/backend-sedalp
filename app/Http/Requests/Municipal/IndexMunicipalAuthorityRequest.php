<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use App\Enums\Municipal\MunicipalAuthorityRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMunicipalAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'role' => ['sometimes', Rule::enum(MunicipalAuthorityRole::class)],
            'is_current' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_current', 'is_published'] as $field) {
            if (is_string($this->input($field))) {
                $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== null) {
                    $this->merge([$field => $value]);
                }
            }
        }
    }
}
