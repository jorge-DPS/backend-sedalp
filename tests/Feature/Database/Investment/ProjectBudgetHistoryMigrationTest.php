<?php

use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Services\Investment\ProjectBudgetService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    expect(app()->environment('testing'))->toBeTrue()
        ->and(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::selectOne('SELECT current_database() AS name')->name)->toBe('sedalp_testing');

    $this->migration = require database_path('migrations/2026_11_05_202629_add_source_history_to_project_budget_revisions_table.php');
    $this->historyColumns = [
        'previous_investment_source_id', 'previous_source_reference', 'previous_funding_source', 'new_funding_source',
    ];
    $this->functionDefinitions = fn (): array => DB::select(<<<'SQL'
SELECT p.oid, p.proname, pg_get_functiondef(p.oid) AS definition
FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace
WHERE n.nspname = current_schema() AND p.pronargs = 0 AND p.proname IN (
    'simred_guard_project_source', 'simred_guard_source_owner', 'simred_guard_budget_update',
    'simred_guard_budget_revision', 'simred_check_revision_applied'
)
ORDER BY p.proname
SQL);
});

it('añade trazabilidad nullable sin inventar valores para revisiones registradas antes de la mejora', function (): void {
    $this->migration->down();
    $budget = ProjectBudget::factory()->reported('100.00')->create(['funding_source' => 'Recursos propios']);
    $source = InvestmentSource::factory()->create(['institution_id' => $budget->project->institution_id]);
    $id = DB::table('project_budget_revisions')->insertGetId([
        'project_budget_id' => $budget->id, 'revision_number' => 1,
        'previous_amount_bob' => '100.00', 'new_amount_bob' => '200.00',
        'previous_availability_status' => 'reported', 'new_availability_status' => 'reported',
        'investment_source_id' => $source->id, 'source_reference' => 'Documento histórico',
    ]);
    $budget->update(['amount_bob' => '200.00', 'investment_source_id' => $source->id, 'source_reference' => 'Documento histórico']);
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check DEFERRED');
    $before = (array) DB::table('project_budget_revisions')->find($id);

    $this->migration->up();
    $legacy = (array) DB::table('project_budget_revisions')->find($id);

    expect(Arr::except($legacy, $this->historyColumns))->toBe($before)
        ->and(Arr::only($legacy, $this->historyColumns))->toBe(array_fill_keys($this->historyColumns, null));

    app(ProjectBudgetService::class)->revise($budget, [
        'amount_bob' => '300.00', 'availability_status' => 'reported',
        'funding_source' => 'Financiamiento externo',
    ]);
    $next = $budget->revisions()->where('revision_number', 2)->sole();

    expect($next->previous_amount_bob)->toBe('200.00')
        ->and($next->previous_investment_source_id)->toBe($source->id)
        ->and($next->previous_source_reference)->toBe('Documento histórico')
        ->and($next->previous_funding_source)->toBe('Recursos propios')
        ->and($next->new_funding_source)->toBe('Financiamiento externo')
        ->and((array) DB::table('project_budget_revisions')->find($id))->toBe($legacy);

    expect(fn () => DB::transaction(fn () => DB::table('project_budget_revisions')->where('id', $id)->update(['reason' => 'Alteración'])))
        ->toThrow(QueryException::class);
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
});

it('revierte el esquema completo conservando registros legados y restaura las funciones al reaplicar', function (): void {
    $budget = ProjectBudget::factory()->create();
    app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '200.00', 'availability_status' => 'reported']);
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check DEFERRED');
    $beforeBudget = $budget->fresh()->getAttributes();
    $beforeHistory = $budget->revisions()->sole()->getAttributes();
    $indexes = Schema::getIndexes('project_budget_revisions');
    $definitions = ($this->functionDefinitions)();

    expect(Arr::only($beforeHistory, $this->historyColumns))->toBe(array_fill_keys($this->historyColumns, null));

    for ($cycle = 0; $cycle < 2; $cycle++) {
        $this->migration->down();

        foreach ($this->historyColumns as $column) {
            expect(Schema::hasColumn('project_budget_revisions', $column))->toBeFalse();
        }

        expect($budget->fresh()->getAttributes())->toBe($beforeBudget)
            ->and($budget->revisions()->sole()->getAttributes())->toBe(Arr::except($beforeHistory, $this->historyColumns))
            ->and(collect(($this->functionDefinitions)())->pluck('oid')->all())->toBe(collect($definitions)->pluck('oid')->all())
            ->and(collect(($this->functionDefinitions)())->firstWhere('proname', 'simred_guard_budget_update')->definition)->not->toContain('NEW.funding_source');

        $this->migration->up();

        expect(($this->functionDefinitions)())->toEqual($definitions)
            ->and(Schema::getIndexes('project_budget_revisions'))->toBe($indexes)
            ->and($budget->revisions()->sole()->getAttributes())->toBe($beforeHistory)
            ->and(DB::table('pg_trigger')->whereIn('tgname', [
                'projects_source_institution_guard', 'investment_sources_owner_guard', 'project_budgets_update_guard',
                'project_budget_revisions_history_guard', 'project_budget_revision_applied_check',
            ])->where('tgenabled', 'O')->count())->toBe(5);

        expect(fn () => DB::transaction(fn () => $budget->fresh()->update(['funding_source' => 'Sin auditoría'])))
            ->toThrow(QueryException::class);
    }
});

it('bloquea la reversión sin cambiar funciones esquema ni datos cuando existe trazabilidad nueva', function (string $column): void {
    $source = InvestmentSource::factory()->create();
    $project = Project::factory()->create(['institution_id' => $source->institution_id]);
    $budget = ProjectBudget::factory()->reported('100.00')->for($project)->create([
        'investment_source_id' => $column === 'previous_investment_source_id' ? $source->id : null,
        'source_reference' => $column === 'previous_source_reference' ? 'Documento A' : null,
        'funding_source' => $column === 'previous_funding_source' ? 'Recursos propios' : null,
    ]);
    app(ProjectBudgetService::class)->revise($budget, [
        'amount_bob' => '200.00', 'availability_status' => 'reported',
        'investment_source_id' => null, 'source_reference' => null,
        'funding_source' => $column === 'new_funding_source' ? 'Financiamiento externo' : null,
    ]);
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check DEFERRED');
    $beforeBudget = $budget->fresh()->getAttributes();
    $beforeHistory = $budget->revisions()->sole()->getAttributes();
    $definitions = ($this->functionDefinitions)();

    expect($beforeHistory[$column])->not->toBeNull();

    expect(fn () => DB::transaction(fn () => $this->migration->down()))
        ->toThrow(RuntimeException::class, 'No se puede revertir');

    expect($budget->fresh()->getAttributes())->toBe($beforeBudget)
        ->and($budget->revisions()->sole()->getAttributes())->toBe($beforeHistory)
        ->and(Schema::hasColumns('project_budget_revisions', $this->historyColumns))->toBeTrue()
        ->and(($this->functionDefinitions)())->toEqual($definitions);
})->with(['previous_investment_source_id', 'previous_source_reference', 'previous_funding_source', 'new_funding_source']);
