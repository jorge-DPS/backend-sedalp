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
        return [
            'id' => $this->id,
            'municipal_poa_id' => $this->municipal_poa_id,
            'version_number' => $this->version_number,
            'title' => $this->title,
            'document_reference' => $this->document_reference,
            'is_current' => $this->is_current,
            'published_at' => $this->published_at,
            'document_type' => $this->document_type->value,
            'download_url' => route('public.municipal.documents.file', [
                'municipality' => $request->route('municipality'),
                'municipalPoa' => $this->municipal_poa_id, 'document' => $this->id,
            ], false),
        ];
    }
}
