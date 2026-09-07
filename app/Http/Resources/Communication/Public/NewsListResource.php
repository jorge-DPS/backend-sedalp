<?php

namespace App\Http\Resources\Communication\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'excerpt' => $this->excerpt,
            'publishedAt' => $this->published_at
                ?->toDateString(),
            'coverImage' => $this->whenLoaded(
                'coverImage',
                fn () => $this->coverImage
                    ? new NewsImageResource($this->coverImage)
                    : null
            ),
        ];
    }
}
