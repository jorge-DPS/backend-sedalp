<?php

namespace Database\Factories\Investment;

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectBudgetRevision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends Factory<ProjectBudgetRevision> */
class ProjectBudgetRevisionFactory extends Factory
{
    protected $model = ProjectBudgetRevision::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_budget_id' => ProjectBudget::factory(),
            'revision_number' => fn (array $attributes): int => (int) ProjectBudget::findOrFail($attributes['project_budget_id'])->revisions()->max('revision_number') + 1,
            'previous_amount_bob' => fn (array $attributes): ?string => ProjectBudget::findOrFail($attributes['project_budget_id'])->amount_bob,
            'previous_availability_status' => fn (array $attributes): BudgetAvailabilityStatus => ProjectBudget::findOrFail($attributes['project_budget_id'])->availability_status,
            'previous_investment_source_id' => fn (array $attributes): ?int => ProjectBudget::findOrFail($attributes['project_budget_id'])->investment_source_id,
            'previous_source_reference' => fn (array $attributes): ?string => ProjectBudget::findOrFail($attributes['project_budget_id'])->source_reference,
            'previous_funding_source' => fn (array $attributes): ?string => ProjectBudget::findOrFail($attributes['project_budget_id'])->funding_source,
            'new_funding_source' => fn (array $attributes): ?string => ProjectBudget::findOrFail($attributes['project_budget_id'])->funding_source,
            'new_amount_bob' => '1000.00',
            'new_availability_status' => BudgetAvailabilityStatus::REPORTED,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ProjectBudgetRevision $revision): void {
            $revision->projectBudget->update([
                'amount_bob' => $revision->new_amount_bob,
                'availability_status' => $revision->new_availability_status,
                'investment_source_id' => $revision->investment_source_id,
                'source_reference' => $revision->source_reference,
                'funding_source' => $revision->new_funding_source,
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function create($attributes = [], ?Model $parent = null): Model|Collection
    {
        return DB::transaction(fn (): Model|Collection => parent::create($attributes, $parent));
    }
}
