<?php

namespace Database\Factories\Investment;

use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array{institution_id: InstitutionFactory, name: string} */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->sentence(8),
        ];
    }

    public function classified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sector_id' => Sector::factory(),
            'strategic_axis_id' => StrategicAxis::factory(),
            'intervention_type_id' => InterventionType::factory(),
            'project_status_id' => ProjectStatus::factory(),
            'main_province_id' => Province::factory(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()]);
    }

    public function withTotalCost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'total_cost_bob' => fake()->numberBetween(1, 999999999).'.'.str_pad((string) fake()->numberBetween(0, 99), 2, '0', STR_PAD_LEFT),
        ]);
    }
}
