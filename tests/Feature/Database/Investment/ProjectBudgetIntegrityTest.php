<?php

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\Institution;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectBudgetRevision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('distingue la institución propietaria del proveedor y relaciona fuentes proyectos presupuestos y revisiones', function (): void {
    $institution = Institution::factory()->create(['code' => 'ABC']);
    $source = InvestmentSource::factory()->for($institution)->create(['provided_by_name' => 'Entidad proveedora de prueba']);
    $projects = Project::factory()->for($institution)->count(2)->create(['primary_investment_source_id' => $source->id]);
    $budget = ProjectBudget::factory()->for($projects[0])->create(['investment_source_id' => $source->id]);
    $revision = ProjectBudgetRevision::factory()->for($budget)->create(['investment_source_id' => $source->id]);

    expect($source->institution->is($institution))->toBeTrue()
        ->and($source->provided_by_name)->toBe('Entidad proveedora de prueba')
        ->and($institution->investmentSources()->sole()->is($source))->toBeTrue()
        ->and($source->primaryProjects()->count())->toBe(2)
        ->and($projects[0]->primaryInvestmentSource->is($source))->toBeTrue()
        ->and($projects[0]->budgets()->sole()->is($budget))->toBeTrue()
        ->and($budget->project->is($projects[0]))->toBeTrue()
        ->and($budget->investmentSource->is($source))->toBeTrue()
        ->and($source->projectBudgets()->sole()->is($budget))->toBeTrue()
        ->and($source->budgetRevisions()->sole()->is($revision))->toBeTrue()
        ->and($revision->investmentSource->is($source))->toBeTrue()
        ->and($revision->projectBudget->is($budget))->toBeTrue()
        ->and($budget->revisions()->sole()->is($revision))->toBeTrue();
});

it('admite varias gestiones y no duplica el presupuesto anual de un proyecto', function (): void {
    $project = Project::factory()->create();
    $budget = ProjectBudget::factory()->for($project)->create(['fiscal_year' => 2026]);
    ProjectBudget::factory()->for($project)->create(['fiscal_year' => 2027]);

    expect($project->budgets()->count())->toBe(2);

    expect(fn () => DB::transaction(fn () => ProjectBudget::factory()->for($project)->create(['fiscal_year' => $budget->fiscal_year])))
        ->toThrow(QueryException::class);
});

it('conserva precisión decimal sin convertir presupuestos desconocidos en cero', function (?string $amount): void {
    $budget = ProjectBudget::factory()->create([
        'amount_bob' => $amount,
        'availability_status' => $amount === null ? BudgetAvailabilityStatus::NOT_AVAILABLE : BudgetAvailabilityStatus::REPORTED,
    ])->fresh();

    expect($budget->amount_bob)->toBe($amount)
        ->and($budget->toArray()['amount_bob'])->toBe($amount);
})->with([null, '0.00', '0.10', '1234567890123456.78', '9999999999999999.99']);

it('admite proyectos ABC con costo total conocido y presupuesto anual no disponible', function (): void {
    $project = Project::factory()->create(['total_cost_bob' => '123456.78']);
    $budget = ProjectBudget::factory()->for($project)->create();

    expect($budget->fresh()->amount_bob)->toBeNull()
        ->and($budget->availability_status)->toBe(BudgetAvailabilityStatus::NOT_AVAILABLE)
        ->and($project->fresh()->total_cost_bob)->toBe('123456.78')
        ->and($budget->investment_source_id)->toBeNull();
});

it('rechaza importes estados y gestiones incoherentes en PostgreSQL', function (array $changes): void {
    $attributes = ProjectBudget::factory()->make()->getAttributes();

    expect(fn () => DB::transaction(fn () => DB::table('project_budgets')->insert([...$attributes, ...$changes])))
        ->toThrow(QueryException::class);
})->with([
    [['amount_bob' => '-0.01', 'availability_status' => 'reported']],
    [['amount_bob' => 'NaN', 'availability_status' => 'reported']],
    [['amount_bob' => null, 'availability_status' => 'reported']],
    [['amount_bob' => '0.00', 'availability_status' => 'not_available']],
    [['availability_status' => 'unknown']],
    [['fiscal_year' => 1899]],
    [['fiscal_year' => 2101]],
]);

it('rechaza fuentes de otra institución también en escrituras directas', function (string $target): void {
    $project = Project::factory()->create();
    $foreign = InvestmentSource::factory()->create();
    $budget = ProjectBudget::factory()->for($project)->create();

    expect(fn () => DB::transaction(fn () => match ($target) {
        'project' => $project->update(['primary_investment_source_id' => $foreign->id]),
        'budget' => ProjectBudget::factory()->for($project)->create(['fiscal_year' => 2027, 'investment_source_id' => $foreign->id]),
        'revision' => ProjectBudgetRevision::factory()->for($budget)->create(['investment_source_id' => $foreign->id]),
    }))->toThrow(QueryException::class);
})->with(['project', 'budget', 'revision']);

it('impide alterar instituciones cuando se invalidaría una fuente vinculada', function (string $target): void {
    $project = Project::factory()->create();
    $source = InvestmentSource::factory()->create(['institution_id' => $project->institution_id]);
    $project->update(['primary_investment_source_id' => $source->id]);
    $other = Institution::factory()->create();

    expect(fn () => DB::transaction(fn () => $target === 'source'
        ? $source->update(['institution_id' => $other->id])
        : $project->update(['institution_id' => $other->id])))
        ->toThrow(QueryException::class);
})->with(['source', 'project']);

it('impide modificar un monto silenciosamente mediante Eloquent o query builder', function (bool $queryBuilder): void {
    $budget = ProjectBudget::factory()->reported()->create();

    expect(fn () => DB::transaction(fn () => $queryBuilder
        ? DB::table('project_budgets')->where('id', $budget->id)->update(['amount_bob' => '2000.00'])
        : $budget->update(['amount_bob' => '2000.00'])))
        ->toThrow(QueryException::class);

    expect($budget->fresh()->amount_bob)->toBe('1000.00');
})->with([false, true]);

it('conserva inmutables las revisiones presupuestarias', function (bool $delete): void {
    $revision = ProjectBudgetRevision::factory()->create();

    expect(fn () => DB::transaction(fn () => $delete ? $revision->delete() : $revision->update(['reason' => 'Sobrescritura'])))
        ->toThrow(QueryException::class);

    $this->assertModelExists($revision);
})->with([false, true]);

it('no permite confirmar una revisión sin actualizar el valor vigente', function (): void {
    $attributes = ProjectBudgetRevision::factory()->make()->getAttributes();

    expect(fn () => DB::transaction(function () use ($attributes): void {
        DB::table('project_budget_revisions')->insert($attributes);
        DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    }))->toThrow(QueryException::class);
});

it('protege las claves foráneas y el borrado físico de las fuentes y presupuestos', function (string $model, string $foreignKey, string $relation): void {
    $record = $model::factory()->create();
    $parent = $record->{$relation};

    expect(fn () => DB::transaction(fn () => $record->update([$foreignKey => 999999999])))
        ->toThrow(QueryException::class);

    expect(fn () => DB::transaction(fn () => method_exists($parent, 'forceDelete') ? $parent->forceDelete() : $parent->delete()))
        ->toThrow(QueryException::class);
})->with([
    [InvestmentSource::class, 'institution_id', 'institution'],
    [ProjectBudget::class, 'project_id', 'project'],
    [ProjectBudgetRevision::class, 'project_budget_id', 'projectBudget'],
]);

it('rechaza claves foráneas de inversión inexistentes también al insertar', function (string $model, string $foreignKey): void {
    $attributes = $model::factory()->make()->getAttributes();
    $attributes[$foreignKey] = 999999999;

    expect(fn () => DB::transaction(fn () => DB::table((new $model)->getTable())->insert($attributes)))
        ->toThrow(QueryException::class);
})->with([
    [InvestmentSource::class, 'institution_id'],
    [Project::class, 'primary_investment_source_id'],
    [ProjectBudget::class, 'project_id'],
    [ProjectBudget::class, 'investment_source_id'],
    [ProjectBudgetRevision::class, 'project_budget_id'],
    [ProjectBudgetRevision::class, 'investment_source_id'],
]);
