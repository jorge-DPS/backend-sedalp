<?php

namespace App\Http\Requests\Territory\Public;

use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTerritorialCatalogRequest extends FormRequest
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
            'region_id' => ['sometimes', 'integer', 'min:1', Rule::exists(Region::class, 'id')->withoutTrashed()->where('active', true)],
            'province_id' => ['sometimes', 'integer', 'min:1', Rule::exists(Province::class, 'id')->withoutTrashed()->where('active', true)],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
        ];
    }
}
