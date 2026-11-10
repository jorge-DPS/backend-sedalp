<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class PublishMunicipalAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_PUBLISH->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'is_published' => ['required', 'boolean'],
            'municipality_id' => ['missing'],
            'is_current' => ['missing'],
            'photo_path' => ['missing'],
            'role' => ['missing'],
        ];
    }
}
