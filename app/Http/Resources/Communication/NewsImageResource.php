<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NewsImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storageUrl = Storage::disk('public')
            ->url($this->path);

        $imageUrl = str_starts_with($storageUrl, 'http')
            ? $storageUrl
            : url($storageUrl);

        return [
            'id' => $this->id,
            'url' => $imageUrl,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'position' => $this->position,
        ];
    }
}
