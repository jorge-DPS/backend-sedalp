<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class PublishMunicipalPoaDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_DOCUMENTS_PUBLISH->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'municipal_poa_id' => ['missing'],
            'version_number' => ['missing'],
            'document_type' => ['missing'],
            'is_current' => ['missing'],
            'published_at' => ['missing'],
            'pdf_path' => ['missing'],
        ];
    }
}
