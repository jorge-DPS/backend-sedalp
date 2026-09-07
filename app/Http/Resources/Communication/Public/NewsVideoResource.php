<?php

namespace App\Http\Resources\Communication\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'youtubeUrl' => $this->youtube_url,
            'title' => $this->title,
            'position' => $this->position,
        ];
    }
}
