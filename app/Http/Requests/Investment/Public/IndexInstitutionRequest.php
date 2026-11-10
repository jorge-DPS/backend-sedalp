<?php

namespace App\Http\Requests\Investment\Public;

use Illuminate\Foundation\Http\FormRequest;

class IndexInstitutionRequest extends FormRequest
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
        ];
    }
}
