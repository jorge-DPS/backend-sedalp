<?php

namespace App\Http\Requests\Territory;

use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMunicipalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('municipalities.view') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'region_id' => ['sometimes', 'integer', Rule::exists(Region::class, 'id')->withoutTrashed()],
            'province_id' => ['sometimes', 'integer', Rule::exists(Province::class, 'id')->withoutTrashed()],
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
