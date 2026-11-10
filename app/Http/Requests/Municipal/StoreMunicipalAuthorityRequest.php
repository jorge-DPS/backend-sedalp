<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use App\Enums\Municipal\MunicipalAuthorityRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMunicipalAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'role' => ['required', Rule::enum(MunicipalAuthorityRole::class)],
            'position_title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'term_start' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'term_end' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'is_current' => ['sometimes', 'boolean'],
            'source_reference' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'municipality_id' => ['missing'],
            'photo_path' => ['missing'],
            'photo' => ['missing'],
            'is_published' => ['missing'],
        ];
    }
}
