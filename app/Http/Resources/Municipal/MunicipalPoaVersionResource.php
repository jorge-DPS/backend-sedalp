<?php

namespace App\Http\Resources\Municipal;

use App\Models\Municipal\MunicipalPoaVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalPoaVersion */
class MunicipalPoaVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'municipal_poa_id' => $this->municipal_poa_id,
            'version_number' => $this->version_number, 'document_type' => $this->document_type->value,
            'published_at' => $this->published_at, 'is_published' => $this->isPubliclyAvailable(),
            'is_current' => $this->is_current, 'documents_count' => $this->whenCounted('documents'),
            'documents' => MunicipalPoaDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
