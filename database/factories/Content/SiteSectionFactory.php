<?php

namespace Database\Factories\Content;

use App\Models\Content\SitePage;
use App\Models\Content\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteSection> */
class SiteSectionFactory extends Factory
{
    protected $model = SiteSection::class;

    public function definition(): array
    {
        return ['site_page_id' => SitePage::factory(), 'key' => fake()->unique()->slug(2), 'type' => 'hero', 'title' => fake()->sentence(), 'description' => fake()->paragraph()];
    }
}
