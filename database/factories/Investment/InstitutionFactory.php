<?php

namespace Database\Factories\Investment;

use App\Models\Investment\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Institution> */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    /** @return array{code: string, name: string, active: bool} */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TEST-########'),
            'name' => fake()->unique()->sentence(4),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
