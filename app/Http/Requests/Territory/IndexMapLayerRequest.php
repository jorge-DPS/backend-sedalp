<?php

namespace App\Http\Requests\Territory;

use Illuminate\Foundation\Http\FormRequest;

class IndexMapLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('maps.view') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'region_id' => ['nullable', 'integer'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
