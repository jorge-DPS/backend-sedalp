<?php

namespace App\Services\Investment\Public;

use App\Models\Investment\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ProjectService
{
    /** @param array<string, mixed> $filters
     * @return Builder<Project>
     */
    public function query(array $filters): Builder
    {
        $query = Project::query()->publiclyAvailable();
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
                $query->whereLike('name', '%'.$filters['search'].'%')->orWhereLike('source_code', '%'.$filters['search'].'%');
            });
        }

        return $query;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->withRelations($this->query($filters), $filters)
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_direction'] ?? 'asc')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    public function find(int $id, array $filters): Project
    {
        return $this->withRelations($this->query($filters), $filters)->findOrFail($id);
    }

    /** @param Builder<Project> $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Project>
     */
    private function withRelations(Builder $query, array $filters): Builder
    {
        foreach (['institution', 'sector', 'strategicAxis', 'interventionType', 'projectStatus', 'mainProvince'] as $relation) {
            $query->with([$relation => function (Relation $relation): void {
                $relation->withTrashed();
            }]);
        }
        $query->with(['provinces', 'municipalities.region', 'municipalities.province']);
        if (isset($filters['fiscal_year'])) {
            $query->with(['budgets' => function (Relation $relation) use ($filters): void {
                $relation->where('fiscal_year', $filters['fiscal_year']);
            }]);
        }

        return $query;
    }
}
