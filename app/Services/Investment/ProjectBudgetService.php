<?php

namespace App\Services\Investment;

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectBudgetService
{
    /** @param array{fiscal_year: int, amount_bob: string|int|null, availability_status: string|BudgetAvailabilityStatus, investment_source_id?: int|null, source_reference?: string|null, funding_source?: string|null, verified_at?: string|null, notes?: string|null} $data */
    public function create(Project $project, array $data): ProjectBudget
    {
        $values = $this->validateValues($data, true);

        return DB::transaction(function () use ($project, $values): ProjectBudget {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->validateSource($project->institution_id, $values['investment_source_id'] ?? null);

            if ($project->budgets()->where('fiscal_year', $values['fiscal_year'])->exists()) {
                throw ValidationException::withMessages(['fiscal_year' => 'Ya existe un presupuesto para esta gestión. Registre una revisión.']);
            }

            return $project->budgets()->create($values);
        });
    }

    /** @param array{amount_bob: string|int|null, availability_status: string|BudgetAvailabilityStatus, investment_source_id?: int|null, source_reference?: string|null, funding_source?: string|null, effective_date?: string|null, reason?: string|null} $data */
    public function revise(ProjectBudget $budget, array $data): ProjectBudget
    {
        return DB::transaction(function () use ($budget, $data): ProjectBudget {
            $budget = ProjectBudget::query()->lockForUpdate()->findOrFail($budget->getKey());
            $project = Project::withTrashed()->sharedLock()->findOrFail($budget->project_id);
            $values = $this->validateValues(array_replace([
                'investment_source_id' => $budget->investment_source_id,
                'source_reference' => $budget->source_reference,
                'funding_source' => $budget->funding_source,
            ], $data), false);
            $this->validateSource($project->institution_id, $values['investment_source_id'] ?? null);

            $current = Arr::only($values, ['amount_bob', 'availability_status', 'investment_source_id', 'source_reference', 'funding_source']);

            if ($budget->amount_bob === $current['amount_bob']
                && $budget->availability_status->value === $current['availability_status']
                && $budget->investment_source_id === $current['investment_source_id']
                && $budget->source_reference === $current['source_reference']
                && $budget->funding_source === $current['funding_source']) {
                return $budget;
            }

            $revision = $budget->revisions()->create([
                'revision_number' => (int) $budget->revisions()->max('revision_number') + 1,
                'previous_amount_bob' => $budget->amount_bob,
                'new_amount_bob' => $current['amount_bob'],
                'previous_availability_status' => $budget->availability_status,
                'new_availability_status' => $current['availability_status'],
                'previous_investment_source_id' => $budget->investment_source_id,
                'previous_source_reference' => $budget->source_reference,
                'previous_funding_source' => $budget->funding_source,
                'investment_source_id' => $current['investment_source_id'],
                'source_reference' => $current['source_reference'],
                'new_funding_source' => $current['funding_source'],
                'effective_date' => $values['effective_date'] ?? null,
                'reason' => $values['reason'] ?? null,
            ]);

            if (! $revision->exists || ! $budget->update($current)) {
                throw new RuntimeException('No se pudo registrar y aplicar la revisión presupuestaria.');
            }

            return $budget->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validateValues(array $data, bool $creating): array
    {
        if (is_int($data['amount_bob'] ?? null)) {
            $data['amount_bob'] = (string) $data['amount_bob'];
        }

        if (($data['availability_status'] ?? null) instanceof BudgetAvailabilityStatus) {
            $data['availability_status'] = $data['availability_status']->value;
        }

        $rules = [
            'amount_bob' => ['present', 'nullable', 'string', 'regex:/^[0-9]{1,16}(?:\\.[0-9]{1,2})?$/D'],
            'availability_status' => ['required', Rule::enum(BudgetAvailabilityStatus::class)],
            'investment_source_id' => ['sometimes', 'nullable', 'integer'],
            'source_reference' => ['sometimes', 'nullable', 'string'],
            'funding_source' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];

        $rules += $creating ? [
            'fiscal_year' => ['required', 'integer', 'between:1900,2100'],
            'verified_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ] : [
            'effective_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reason' => ['sometimes', 'nullable', 'string'],
        ];

        $values = Validator::make($data, $rules)->validate();
        $reported = $values['availability_status'] === BudgetAvailabilityStatus::REPORTED->value;

        if ($reported !== ($values['amount_bob'] !== null)) {
            throw ValidationException::withMessages(['amount_bob' => 'Un monto informado debe existir; un presupuesto no disponible debe conservar NULL.']);
        }

        if ($values['amount_bob'] !== null) {
            $values['amount_bob'] = (string) BigDecimal::of($values['amount_bob'])->toScale(2);
        }

        if (isset($values['investment_source_id'])) {
            $values['investment_source_id'] = (int) $values['investment_source_id'];
        }

        return $values;
    }

    private function validateSource(int $institutionId, ?int $sourceId): void
    {
        if ($sourceId === null) {
            return;
        }

        $source = InvestmentSource::query()->sharedLock()->find($sourceId);

        if ($source === null || $source->institution_id !== $institutionId) {
            throw ValidationException::withMessages(['investment_source_id' => 'La fuente debe existir y corresponder a la institución del proyecto.']);
        }
    }
}
