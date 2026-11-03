<?php

namespace Database\Factories\Investment;

use App\Models\Investment\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectStatus> */
class ProjectStatusFactory extends Factory
{
    protected $model = ProjectStatus::class;

    /** @return array{code: string, name: string, traffic_light: string, active: bool} */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TEST-########'),
            'name' => fake()->unique()->sentence(4),
            'traffic_light' => fake()->randomElement(['green', 'amber', 'red']),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['active' => false]);
    }
}
