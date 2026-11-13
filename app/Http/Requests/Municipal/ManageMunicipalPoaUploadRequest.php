<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class ManageMunicipalPoaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
