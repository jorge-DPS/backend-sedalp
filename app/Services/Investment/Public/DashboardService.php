<?php

namespace App\Services\Investment\Public;

use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use App\Models\Territory\Municipality;
use App\Models\Territory\MunicipalityRegionMembership;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const DIMENSIONS = [
        'institutions' => ['institution_id', Institution::class],
        'sectors' => ['sector_id', Sector::class],
        'strategic_axes' => ['strategic_axis_id', StrategicAxis::class],
        'intervention_types' => ['intervention_type_id', InterventionType::class],
        'project_statuses' => ['project_status_id', ProjectStatus::class],
    ];

    public function __construct(private readonly ProjectService $projects) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $row = DB::query()->fromSub($this->data($filters), 'selected_projects')->selectRaw($this->aggregateSql())->first();

        return $this->metrics($row, isset($filters['fiscal_year']) ? (int) $filters['fiscal_year'] : null);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, list<array<string, mixed>>>
     */
    public function distributions(array $filters): array
    {
        $result = [];
        foreach (self::DIMENSIONS as $name => [$field, $model]) {
            $rows = DB::query()->fromSub($this->data($filters), 'selected_projects')
                ->select('selected_projects.'.$field.' as category_id')->selectRaw($this->aggregateSql())
                ->groupBy('selected_projects.'.$field)->orderBy('category_id')->get();
            $labels = $model::withTrashed()->whereKey($rows->pluck('category_id')->filter()->all())->get()->keyBy('id');
            $result[$name] = $rows->map(function (object $row) use ($labels, $filters): array {
                $label = $labels->get($row->category_id);

                return [
                    'category' => [
                        'id' => $row->category_id === null ? null : (int) $row->category_id,
                        'name' => $label?->name ?? 'Sin clasificación',
                        'code' => $label?->getAttribute('code'),
                        'traffic_light' => $label?->getAttribute('traffic_light'),
                    ],
                    'metrics' => $this->metrics($row, isset($filters['fiscal_year']) ? (int) $filters['fiscal_year'] : null),
                ];
            })->all();
        }

        return $result;
    }

    /** @param array<string, mixed> $filters
     * @return array{results: LengthAwarePaginator, unassigned: array<string, mixed>}
     */
    public function territories(string $type, array $filters): array
    {
        $map = $this->territorialMemberships($type, $filters['region_date'] ?? null);
        $model = match ($type) {
            'regions' => Region::class, 'provinces' => Province::class, 'municipalities' => Municipality::class,
        };
        $results = DB::query()->fromSub(clone $map, 'territorial_memberships')
            ->joinSub($this->data($filters), 'selected_projects', 'selected_projects.id', '=', 'territorial_memberships.project_id')
            ->select('territorial_memberships.territory_id')->selectRaw($this->aggregateSql())
            ->groupBy('territorial_memberships.territory_id')->orderBy('territory_id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
        $labels = $model::query()->whereKey(collect($results->items())->pluck('territory_id')->all())->get()->keyBy('id');
        $year = isset($filters['fiscal_year']) ? (int) $filters['fiscal_year'] : null;
        $results->through(fn (object $row): array => [
            'territory' => ['id' => (int) $row->territory_id, 'name' => $labels->get($row->territory_id)?->name],
            'metrics' => $this->metrics($row, $year),
        ]);
        $assigned = (clone $map)->select('beneficiaries.project_id')->distinct();
        $unassigned = DB::query()->fromSub($this->data($filters), 'selected_projects')
            ->whereNotIn('selected_projects.id', $assigned)->selectRaw($this->aggregateSql())->first();

        return ['results' => $results, 'unassigned' => $this->metrics($unassigned, $year)];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function metadata(array $filters): array
    {
        return [
            'coverage' => 'available_published_records',
            'description' => 'Suma de los datos publicados disponibles en SIMRED. La ausencia de registros no certifica ausencia de proyectos en la realidad.',
            'currency' => 'BOB',
            'source_year' => isset($filters['source_year']) ? (int) $filters['source_year'] : null,
            'fiscal_year' => isset($filters['fiscal_year']) ? (int) $filters['fiscal_year'] : null,
            'region_date' => $filters['region_date'] ?? null,
            'budget_year_selection' => 'La gestión selecciona el presupuesto medido; conserva los proyectos sin registro presupuestario.',
            'territorial_totals_are_additive' => false,
            'territorial_amounts' => 'Cada territorio muestra el importe completo de sus proyectos identificados. Las filas pueden superponerse y no deben sumarse para reconstruir el total general.',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function data(array $filters): Builder
    {
        $table = (new Project)->getTable();
        $query = $this->projects->query($filters)->select([
            $table.'.id', $table.'.institution_id', $table.'.sector_id', $table.'.strategic_axis_id',
            $table.'.intervention_type_id', $table.'.project_status_id', $table.'.project_condition', $table.'.total_cost_bob',
        ]);
        if (isset($filters['fiscal_year'])) {
            $query->leftJoin((new ProjectBudget)->getTable().' as annual_budget', function (JoinClause $join) use ($filters, $table): void {
                $join->on('annual_budget.project_id', '=', $table.'.id')->where('annual_budget.fiscal_year', '=', $filters['fiscal_year']);
            })->addSelect(['annual_budget.id as budget_id', 'annual_budget.amount_bob as budget_amount_bob', 'annual_budget.availability_status as budget_availability_status']);
        } else {
            $query->selectRaw('NULL::bigint AS budget_id, NULL::numeric AS budget_amount_bob, NULL::text AS budget_availability_status');
        }

        return $query->toBase();
    }

    private function aggregateSql(): string
    {
        return <<<'SQL'
COUNT(*) AS project_count,
SUM(selected_projects.total_cost_bob)::text AS total_known_cost_bob,
COUNT(*) FILTER (WHERE selected_projects.total_cost_bob > 0) AS cost_positive_count,
COUNT(*) FILTER (WHERE selected_projects.total_cost_bob = 0) AS cost_zero_count,
COUNT(*) FILTER (WHERE selected_projects.total_cost_bob IS NULL) AS cost_unknown_count,
COUNT(*) FILTER (WHERE selected_projects.budget_id IS NOT NULL) AS budget_recorded_count,
SUM(selected_projects.budget_amount_bob) FILTER (WHERE selected_projects.budget_availability_status = 'reported')::text AS total_known_budget_bob,
COUNT(*) FILTER (WHERE selected_projects.budget_availability_status = 'reported' AND selected_projects.budget_amount_bob > 0) AS budget_positive_count,
COUNT(*) FILTER (WHERE selected_projects.budget_availability_status = 'reported' AND selected_projects.budget_amount_bob = 0) AS budget_zero_count,
COUNT(*) FILTER (WHERE selected_projects.budget_availability_status = 'not_available') AS budget_unavailable_count,
COUNT(*) FILTER (WHERE selected_projects.budget_id IS NULL) AS budget_missing_count,
COUNT(*) FILTER (WHERE selected_projects.project_condition = 'new') AS new_count,
COUNT(*) FILTER (WHERE selected_projects.project_condition = 'continuing') AS continuing_count,
COUNT(*) FILTER (WHERE selected_projects.project_condition IS NULL) AS condition_unknown_count
SQL;
    }

    /** @return array<string, mixed> */
    private function metrics(object $row, ?int $year): array
    {
        return [
            'project_count' => (int) $row->project_count,
            'has_registered_data' => (int) $row->project_count > 0,
            'total_cost' => [
                'known_amount_bob' => $row->total_known_cost_bob,
                'reported_positive_count' => (int) $row->cost_positive_count,
                'reported_zero_count' => (int) $row->cost_zero_count,
                'unknown_count' => (int) $row->cost_unknown_count,
            ],
            'annual_budget' => $year === null ? null : [
                'fiscal_year' => $year,
                'known_amount_bob' => $row->total_known_budget_bob,
                'recorded_count' => (int) $row->budget_recorded_count,
                'reported_positive_count' => (int) $row->budget_positive_count,
                'reported_zero_count' => (int) $row->budget_zero_count,
                'unavailable_count' => (int) $row->budget_unavailable_count,
                'missing_record_count' => (int) $row->budget_missing_count,
            ],
            'conditions' => [
                'new_count' => (int) $row->new_count, 'continuing_count' => (int) $row->continuing_count,
                'unknown_count' => (int) $row->condition_unknown_count,
            ],
        ];
    }

    private function territorialMemberships(string $type, ?string $date): Builder
    {
        $project = new Project;
        if ($type === 'provinces') {
            return DB::table($project->provinces()->getTable().' as beneficiaries')
                ->join((new Province)->getTable().' as territory', 'territory.id', '=', 'beneficiaries.province_id')
                ->whereNull('territory.deleted_at')->select(['beneficiaries.project_id', 'territory.id as territory_id'])->distinct();
        }

        $query = DB::table($project->municipalities()->getTable().' as beneficiaries')
            ->join((new Municipality)->getTable().' as municipalities', 'municipalities.id', '=', 'beneficiaries.municipality_id')
            ->whereNull('municipalities.deleted_at');
        if ($type === 'municipalities') {
            return $query->select(['beneficiaries.project_id', 'municipalities.id as territory_id'])->distinct();
        }

        $regionColumn = 'municipalities.region_id';
        if ($date !== null) {
            $query->join((new MunicipalityRegionMembership)->getTable().' as memberships', 'memberships.municipality_id', '=', 'municipalities.id')
                ->where('memberships.valid_from', '<=', $date)
                ->where(fn (Builder $query): Builder => $query->whereNull('memberships.valid_to')->orWhere('memberships.valid_to', '>', $date));
            $regionColumn = 'memberships.region_id';
        }

        return $query->join((new Region)->getTable().' as territory', 'territory.id', '=', $regionColumn)
            ->whereNull('territory.deleted_at')->select(['beneficiaries.project_id', 'territory.id as territory_id'])->distinct();
    }
}
