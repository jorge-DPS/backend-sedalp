<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiteMediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile' => $this->profile,
            'url' => Storage::disk($this->disk)->url($this->path),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toISOString(),
            'in_use' => $this->when(isset($this->desktop_sections_exists), fn (): bool => (bool) ($this->desktop_sections_exists || $this->mobile_sections_exists)),
        ];
    }
}
