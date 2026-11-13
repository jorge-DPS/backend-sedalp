<?php

namespace App\Http\Requests\Municipal;

class StartMunicipalPoaUploadRequest extends ManageMunicipalPoaUploadRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'expected_size' => ['required', 'integer', 'min:1', 'max:'.config('municipal.poa_max_file_bytes')],
            'sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'original_filename' => ['required', 'string', 'max:255', 'regex:~^[^/\\\\\x00-\x1F\x7F]+\\.pdf$~iuD'],
            'title' => ['required', 'string', 'max:255'],
            'document_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'received_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
