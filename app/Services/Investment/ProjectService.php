<?php

namespace App\Services\Investment;

use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public const RELATIONS = [
        'institution', 'sector', 'strategicAxis', 'interventionType', 'projectStatus',
        'mainProvince', 'primaryInvestmentSource', 'provinces', 'municipalities.region', 'municipalities.province',
    ];

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Project::query()->with(self::RELATIONS);

        foreach (['institution_id', 'sector_id', 'strategic_axis_id', 'intervention_type_id', 'project_status_id', 'source_year'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['province_id'])) {
            $query->inProvince((int) $filters['province_id']);
        }

        if (isset($filters['municipality_id'])) {
            $query->inMunicipality((int) $filters['municipality_id']);
        }

        if (isset($filters['region_id'])) {
            if (isset($filters['region_date'])) {
                $query->inRegionOn((int) $filters['region_id'], $filters['region_date']);
            } else {
                $query->inCurrentRegion((int) $filters['region_id']);
            }
        }

        if (filled($filters['search'] ?? null)) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereLike('name', '%'.$filters['search'].'%')
                    ->orWhereLike('source_code', '%'.$filters['search'].'%');
            });
        }

        return $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_direction'] ?? 'desc')
            ->orderBy('id', 'desc')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $this->lockSelections($data);
            $project = new Project(Arr::except($data, ['province_ids', 'municipality_ids']));
            $this->validateSources($project, (int) $data['institution_id'], $data['primary_investment_source_id'] ?? null);
            $project->save();
            $this->syncTerritories($project, $data);

            return $project->load(self::RELATIONS);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $this->lockSelections($data);
            $sourceId = array_key_exists('primary_investment_source_id', $data)
                ? $data['primary_investment_source_id'] : $project->primary_investment_source_id;
            $this->validateSources($project, (int) ($data['institution_id'] ?? $project->institution_id), $sourceId);
            $project->update(Arr::except($data, ['province_ids', 'municipality_ids']));
            $this->syncTerritories($project, $data);

            return $project->refresh()->load(self::RELATIONS);
        }, 3);
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            Project::query()->lockForUpdate()->findOrFail($project->getKey())->delete();
        });
    }

    public function publish(Project $project, bool $published): Project
    {
        return DB::transaction(function () use ($project, $published): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $project->published_at = $published ? ($project->isPubliclyAvailable() ? $project->published_at : now()) : null;
            if (! $project->save()) {
                throw new \RuntimeException('No se pudo actualizar la publicación del proyecto.');
            }

            return $project->refresh()->load(self::RELATIONS);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function lockSelections(array $data): void
    {
        foreach ([
            'institution_id' => Institution::class,
            'sector_id' => Sector::class,
            'strategic_axis_id' => StrategicAxis::class,
            'intervention_type_id' => InterventionType::class,
            'project_status_id' => ProjectStatus::class,
            'main_province_id' => Province::class,
        ] as $field => $model) {
            if (isset($data[$field]) && $model::query()->where('active', true)->sharedLock()->find($data[$field]) === null) {
                throw ValidationException::withMessages([$field => 'La opción seleccionada no está disponible.']);
            }
        }

        foreach (['province_ids' => Province::class, 'municipality_ids' => Municipality::class] as $field => $model) {
            if (isset($data[$field])) {
                $available = $model::query()->whereKey($data[$field])->where('active', true)->orderBy('id')->sharedLock()->get(['id']);

                if ($available->count() !== count($data[$field])) {
                    throw ValidationException::withMessages([$field => 'Los territorios seleccionados deben existir y estar disponibles.']);
                }
            }
        }
    }

    private function validateSources(Project $project, int $institutionId, int|string|null $sourceId): void
    {
        if ($sourceId !== null) {
            $source = InvestmentSource::query()->sharedLock()->find($sourceId);

            if ($source === null || $source->institution_id !== $institutionId) {
                throw ValidationException::withMessages(['primary_investment_source_id' => 'La fuente debe corresponder a la institución del proyecto.']);
            }
        }

        if ($project->exists && $institutionId !== $project->institution_id) {
            $foreignSource = fn (Builder $query): Builder => $query->where('institution_id', '<>', $institutionId);

            if ($project->budgets()->whereHas('investmentSource', $foreignSource)->exists()
                || $project->budgets()->whereHas('revisions.investmentSource', $foreignSource)->exists()
                || $project->budgets()->whereHas('revisions.previousInvestmentSource', $foreignSource)->exists()) {
                throw ValidationException::withMessages(['institution_id' => 'El cambio invalidaría las fuentes del historial presupuestario.']);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function syncTerritories(Project $project, array $data): void
    {
        if (array_key_exists('province_ids', $data)) {
            $project->provinces()->sync($data['province_ids']);
        }

        if (array_key_exists('municipality_ids', $data)) {
            $project->municipalities()->sync($data['municipality_ids']);
        }
    }
}
