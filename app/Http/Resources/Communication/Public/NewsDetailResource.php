<?php

namespace App\Http\Resources\Communication\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'publishedAt' => $this->published_at
                ?->toDateString(),
            'images' => $this->relationLoaded('images')
                ? NewsImageResource::collection($this->images)
                : [],
            'videos' => $this->relationLoaded('videos')
                ? NewsVideoResource::collection($this->videos)
                : [],
        ];
    }
}
