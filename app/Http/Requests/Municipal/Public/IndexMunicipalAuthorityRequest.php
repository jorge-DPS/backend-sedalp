<?php

namespace App\Http\Requests\Municipal\Public;

use App\Enums\Municipal\MunicipalAuthorityRole;
use App\Models\Municipal\MunicipalTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMunicipalAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'municipal_term_id' => ['sometimes', 'integer', Rule::exists(MunicipalTerm::class, 'id')->where('municipality_id', $this->route('municipality')?->id)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'role' => ['sometimes', Rule::enum(MunicipalAuthorityRole::class)],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_current'] as $field) {
            if (is_string($this->input($field))) {
                $value = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value !== null) {
                    $this->merge([$field => $value]);
                }
            }
        }
    }
}
