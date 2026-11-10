<?php

namespace App\Http\Requests\Municipal\Public;

use Illuminate\Foundation\Http\FormRequest;

class IndexMunicipalPoaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'fiscal_year' => ['sometimes', 'integer', 'between:1900,2100'],
        ];
    }
}
