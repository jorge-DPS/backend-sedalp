<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class IndexMunicipalTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_AUTHORITIES_VIEW->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']];
    }
}
