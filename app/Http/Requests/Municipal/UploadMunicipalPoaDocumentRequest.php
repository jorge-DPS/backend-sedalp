<?php

namespace App\Http\Requests\Municipal;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class UploadMunicipalPoaDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::MUNICIPAL_DOCUMENTS_UPLOAD->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'pdf' => ['bail', 'required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:'.config('municipal.poa_max_upload_kb')],
            'title' => ['required', 'string', 'max:255'],
            'document_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'received_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'municipal_poa_id' => ['missing'],
            'version_number' => ['missing'],
            'document_type' => ['missing'],
            'is_current' => ['missing'],
            'published_at' => ['missing'],
            'pdf_path' => ['missing'],
            'file_size_bytes' => ['missing'],
            'mime_type' => ['missing'],
            'original_filename' => ['missing'],
        ];
    }
}
