<?php

namespace App\Http\Resources\Municipal;

use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalPoaDocument */
class MunicipalPoaDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipal_poa_id' => $this->municipal_poa_id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'document_reference' => $this->document_reference,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'notes' => $this->notes,
            'document_type' => $this->document_type->value,
            'original_filename' => $this->original_filename === null ? null : basename(str_replace('\\', '/', $this->original_filename)),
            'received_at' => $this->received_at?->toDateString(),
            'published_at' => $this->published_at,
            'is_current' => $this->is_current,
            'is_published' => $this->isPubliclyAvailable(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
