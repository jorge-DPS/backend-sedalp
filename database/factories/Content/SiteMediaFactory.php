<?php

namespace Database\Factories\Content;

use App\Models\Content\SiteMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteMedia> */
class SiteMediaFactory extends Factory
{
    protected $model = SiteMedia::class;

    public function definition(): array
    {
        return ['profile' => 'desktop', 'disk' => 'public', 'path' => 'content/heroes/desktop/'.fake()->uuid().'.webp', 'original_name' => 'banner.jpg', 'mime_type' => 'image/webp', 'size_bytes' => 1024];
    }
}
