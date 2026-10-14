<?php

namespace Database\Factories\Territory;

use App\Models\Territory\MapLayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MapLayer> */
class MapLayerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'kind' => 'overlay', 'created_by' => User::factory()];
    }
}
