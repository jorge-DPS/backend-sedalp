<?php

namespace App\Http\Resources\Municipal\Public;

use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalPoaDocument */
class MunicipalPoaDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $fields = [
            'id' => $this->id,
            'municipal_poa_version_id' => $this->municipal_poa_version_id,
            'original_filename' => $this->original_filename === null ? null : basename(str_replace('\\', '/', $this->original_filename)),
            'municipal_poa_id' => $this->municipal_poa_id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'received_at' => $this->received_at?->toDateString(),
            'document_reference' => $this->document_reference,
            'is_current' => $this->is_current,
            'published_at' => $this->published_at,
            'document_type' => $this->document_type->value,
            'download_url' => route('public.municipal.documents.file', [
                'municipality' => $request->route('municipality'),
                'municipalPoa' => $this->municipal_poa_id, 'document' => $this->id,
            ], false),
        ];
        if ($request->route('version') !== null) {
            $fields = array_diff_key($fields, array_flip(['municipal_poa_id', 'version_number', 'document_type', 'published_at', 'is_current']));
        }

        return $fields;
    }
}
