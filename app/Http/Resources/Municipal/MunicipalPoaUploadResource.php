<?php

namespace App\Http\Resources\Municipal;

use App\Models\Municipal\MunicipalPoaUpload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MunicipalPoaUpload */
class MunicipalPoaUploadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $chunks = $this->metadata['chunks'];
        $indices = array_map('intval', array_keys($chunks));
        sort($indices, SORT_NUMERIC);

        return [
            'id' => $this->id, 'municipal_poa_version_id' => $this->municipal_poa_version_id,
            'status' => $this->status, 'expected_size' => $this->expected_size,
            'sha256' => $this->sha256, 'original_filename' => $this->metadata['document']['original_filename'],
            'title' => $this->metadata['document']['title'],
            'chunk_size' => $this->chunk_size, 'total_chunks' => $this->chunkCount(),
            'received_chunks' => $indices, 'received_bytes' => array_sum(array_column($chunks, 'size')),
            'document_id' => $this->document_id, 'expires_at' => $this->expires_at,
        ];
    }
}
