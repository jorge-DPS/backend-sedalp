<?php

namespace Database\Factories\Investment;

use App\Models\Investment\StrategicAxis;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StrategicAxis> */
class StrategicAxisFactory extends Factory
{
    protected $model = StrategicAxis::class;

    /** @return array{name: string, active: bool} */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(4),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
