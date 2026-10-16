<?php

namespace App\Http\Requests\Territory;

use App\Enums\Territory\MapCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicMapLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', Rule::enum(MapCategory::class)],
            'region_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
