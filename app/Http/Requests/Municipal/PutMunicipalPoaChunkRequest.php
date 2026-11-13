<?php

namespace App\Http\Requests\Municipal;

class PutMunicipalPoaChunkRequest extends ManageMunicipalPoaUploadRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'index' => ['required', 'integer', 'min:0', 'max:'.($this->route('upload')->chunkCount() - 1)],
            'chunk' => ['required', 'file', 'max:'.(int) ceil(config('municipal.poa_chunk_bytes') / 1024)],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return [...parent::validationData(), 'index' => $this->route('index')];
    }
}
