<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'youtubeUrl' => $this->youtube_url,

            'title' => $this->title,

            'position' => $this->position,
        ];
    }
}
