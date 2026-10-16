<?php

namespace Database\Factories\Content;

use App\Models\Content\SiteSection;
use App\Models\Content\SiteSectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteSectionItem> */
class SiteSectionItemFactory extends Factory
{
    protected $model = SiteSectionItem::class;

    public function definition(): array
    {
        return ['site_section_id' => SiteSection::factory()->state(['type' => 'pillars']), 'key' => fake()->unique()->slug(2), 'title' => fake()->sentence(), 'description' => fake()->paragraph()];
    }
}
