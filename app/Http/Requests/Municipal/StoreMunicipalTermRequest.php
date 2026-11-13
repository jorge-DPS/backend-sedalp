<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class StoreMunicipalTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_CREATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'start_year' => ['required', 'integer', 'between:1900,2100'],
            'end_year' => ['required', 'integer', 'between:1900,2100'],
            'municipality_id' => ['missing'], 'name' => ['missing'],
        ];
    }
}
