<?php

namespace App\Http\Requests\Territory;

use Illuminate\Foundation\Http\FormRequest;

class IndexProvinceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('provinces.view') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $search = trim($this->input('search'));
            $this->merge(['search' => $search === '' ? null : $search]);
        }

        if (is_string($this->input('active'))) {
            $active = filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($active !== null) {
                $this->merge(['active' => $active]);
            }
        }
    }
}
