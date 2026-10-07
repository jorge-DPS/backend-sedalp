<?php

namespace Database\Factories\Territory;

use App\Models\Territory\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Province> */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'code' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
