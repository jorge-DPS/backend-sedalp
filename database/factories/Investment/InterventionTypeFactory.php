<?php

namespace Database\Factories\Investment;

use App\Models\Investment\InterventionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InterventionType> */
class InterventionTypeFactory extends Factory
{
    protected $model = InterventionType::class;

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
