<?php

namespace App\Http\Requests\Territory;

use App\Models\Territory\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('regions.update') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique(Region::class, 'name')->ignore($this->route('region'))],
            'code' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique(Region::class, 'code')->ignore($this->route('region'))],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);

                if ($field === 'code') {
                    $value = mb_strtoupper($value);
                }

                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
