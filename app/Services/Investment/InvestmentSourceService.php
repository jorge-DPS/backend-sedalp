<?php

namespace App\Services\Investment;

use App\Models\Investment\Institution;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectBudgetRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestmentSourceService
{
    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, InvestmentSource>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InvestmentSource::query()->with('institution');

        foreach (['institution_id', 'fiscal_year'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereLike('title', '%'.$filters['search'].'%')
                    ->orWhereLike('document_reference', '%'.$filters['search'].'%')
                    ->orWhereLike('provided_by_name', '%'.$filters['search'].'%');
            });
        }

        return $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_direction'] ?? 'desc')
            ->orderBy('id', 'desc')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): InvestmentSource
    {
        return DB::transaction(function () use ($data): InvestmentSource {
            $this->lockInstitution((int) $data['institution_id']);

            return InvestmentSource::create($data)->load('institution');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(InvestmentSource $source, array $data): InvestmentSource
    {
        return DB::transaction(function () use ($source, $data): InvestmentSource {
            $source = InvestmentSource::query()->lockForUpdate()->findOrFail($source->getKey());

            if (isset($data['institution_id'])) {
                $institutionId = (int) $data['institution_id'];
                $this->lockInstitution($institutionId);

                if ($institutionId !== $source->institution_id) {
                    $this->validateOwnership($source, $institutionId);
                }
            }

            $source->update($data);

            return $source->refresh()->load('institution');
        }, 3);
    }

    private function lockInstitution(int $institutionId): void
    {
        if (Institution::query()->where('active', true)->sharedLock()->find($institutionId) === null) {
            throw ValidationException::withMessages(['institution_id' => 'La institución seleccionada no está disponible.']);
        }
    }

    private function validateOwnership(InvestmentSource $source, int $institutionId): void
    {
        $foreignProject = fn (Builder $query): Builder => $query->withTrashed()->where('institution_id', '<>', $institutionId);

        if (Project::withTrashed()->where('primary_investment_source_id', $source->id)->where('institution_id', '<>', $institutionId)->exists()
            || ProjectBudget::query()->where('investment_source_id', $source->id)->whereHas('project', $foreignProject)->exists()
            || ProjectBudgetRevision::query()->where(function (Builder $query) use ($source): void {
                $query->where('investment_source_id', $source->id)->orWhere('previous_investment_source_id', $source->id);
            })->whereHas('projectBudget.project', $foreignProject)->exists()) {
            throw ValidationException::withMessages(['institution_id' => 'La institución debe conservar coherencia con los proyectos y su historial.']);
        }
    }
}
