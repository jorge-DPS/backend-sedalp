<?php

namespace Database\Factories\Investment;

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectBudget> */
class ProjectBudgetFactory extends Factory
{
    protected $model = ProjectBudget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'fiscal_year' => 2026,
            'amount_bob' => null,
            'availability_status' => BudgetAvailabilityStatus::NOT_AVAILABLE,
        ];
    }

    public function reported(string $amount = '1000.00'): static
    {
        return $this->state(fn (array $attributes): array => [
            'amount_bob' => $amount,
            'availability_status' => BudgetAvailabilityStatus::REPORTED,
        ]);
    }
}
