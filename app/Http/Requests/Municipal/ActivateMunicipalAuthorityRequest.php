<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ActivateMunicipalAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_UPDATE->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'previous_authority_id' => ['required', 'integer', 'min:1'],
            'previous_term_end' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'municipality_id' => ['missing'],
            'is_current' => ['missing'],
            'is_published' => ['missing'],
            'role' => ['missing'],
        ];
    }
}
