<?php

namespace App\Http\Resources\Communication;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'slug' => $this->slug,

            'title' => $this->title,

            'subtitle' => $this->subtitle,

            'excerpt' => $this->excerpt,

            'description' => $this->description,

            'content' => $this->content,

            'publishedAt' => $this->published_at
                ?->toDateString(),

            'status' => $this->status->value,

            'images' => $this->relationLoaded('images')
                ? NewsImageResource::collection($this->images)
                : [],

            'videos' => $this->relationLoaded('videos')
                ? NewsVideoResource::collection($this->videos)
                : [],

            'createdBy' => $this->whenLoaded(
                'creator',
                fn () => [
                    'id' => $this->creator->id,
                    'email' => $this->creator->email,
                ]
            ),

            'updatedBy' => $this->whenLoaded(
                'updater',
                fn () => $this->updater
                    ? [
                        'id' => $this->updater->id,
                        'email' => $this->updater->email,
                    ]
                    : null
            ),

            'createdAt' => $this->created_at
                ?->toISOString(),

            'updatedAt' => $this->updated_at
                ?->toISOString(),

            'deletedAt' => $this->deleted_at
                ?->toISOString(),
        ];
    }
}
