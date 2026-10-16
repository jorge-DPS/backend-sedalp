<?php

namespace Database\Factories\Content;

use App\Models\Content\SitePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SitePage> */
class SitePageFactory extends Factory
{
    protected $model = SitePage::class;

    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'name' => fake()->words(3, true)];
    }
}
