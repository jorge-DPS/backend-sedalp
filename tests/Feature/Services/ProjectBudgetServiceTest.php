<?php

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectBudgetRevision;
use App\Services\Investment\ProjectBudgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->budgets = app(ProjectBudgetService::class);
    $this->project = Project::factory()->create(['total_cost_bob' => '999999.99']);
});

it('registra presupuestos anuales sin modificar el costo total y conserva NULL separado de cero', function (): void {
    $unknown = $this->budgets->create($this->project, [
        'fiscal_year' => 2026, 'amount_bob' => null, 'availability_status' => 'not_available',
    ]);
    $zero = $this->budgets->create($this->project, [
        'fiscal_year' => 2027, 'amount_bob' => '0', 'availability_status' => 'reported',
    ]);

    expect($unknown->amount_bob)->toBeNull()
        ->and($zero->amount_bob)->toBe('0.00')
        ->and($this->project->fresh()->total_cost_bob)->toBe('999999.99')
        ->and($this->project->budgets()->count())->toBe(2)
        ->and($unknown->revisions()->exists())->toBeFalse();

    expect(fn () => $this->budgets->create($this->project, [
        'fiscal_year' => 2026, 'amount_bob' => '10.00', 'availability_status' => 'reported',
    ]))->toThrow(ValidationException::class);
});

it('conserva valores anteriores y nuevos con numeración correlativa y un único presupuesto anual', function (): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();
    $stale = $budget->fresh();

    $this->budgets->revise($budget, [
        'amount_bob' => '1234567890123456.78', 'availability_status' => BudgetAvailabilityStatus::REPORTED,
        'source_reference' => 'Informe inicial', 'effective_date' => '2026-01-01', 'reason' => 'Dato recibido',
    ]);
    $this->budgets->revise($stale, [
        'amount_bob' => '9999999999999999.99', 'availability_status' => 'reported',
        'source_reference' => 'Informe rectificado', 'reason' => 'Rectificación acreditada',
    ]);
    $revisions = $budget->revisions()->orderBy('revision_number')->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions->pluck('revision_number')->all())->toBe([1, 2])
        ->and($revisions[0]->previous_amount_bob)->toBeNull()
        ->and($revisions[0]->previous_availability_status)->toBe(BudgetAvailabilityStatus::NOT_AVAILABLE)
        ->and($revisions[0]->new_amount_bob)->toBe('1234567890123456.78')
        ->and($revisions[0]->effective_date->toDateString())->toBe('2026-01-01')
        ->and($revisions[1]->previous_amount_bob)->toBe($revisions[0]->new_amount_bob)
        ->and($budget->fresh()->amount_bob)->toBe('9999999999999999.99')
        ->and($this->project->budgets()->count())->toBe(1)
        ->and($this->project->budgets()->sum('amount_bob'))->toBe('9999999999999999.99')
        ->and($this->project->fresh()->total_cost_bob)->toBe('999999.99');

    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
});

it('acepta cero entero con precisión y admite fuentes distintas de la principal cuando pertenecen a la misma institución', function (): void {
    $primary = InvestmentSource::factory()->create(['institution_id' => $this->project->institution_id]);
    $budgetSource = InvestmentSource::factory()->create(['institution_id' => $this->project->institution_id]);
    $this->project->update(['primary_investment_source_id' => $primary->id]);
    $budget = $this->budgets->create($this->project, [
        'fiscal_year' => 2026, 'amount_bob' => 0, 'availability_status' => 'reported', 'investment_source_id' => $budgetSource->id,
    ]);

    expect($budget->amount_bob)->toBe('0.00')
        ->and($budget->investmentSource->is($budgetSource))->toBeTrue()
        ->and($this->project->fresh()->primaryInvestmentSource->is($primary))->toBeTrue();
});

it('permite pasar de monto reportado a no disponible conservando el monto anterior', function (): void {
    $budget = ProjectBudget::factory()->reported('0.00')->for($this->project)->create();

    $this->budgets->revise($budget, ['amount_bob' => null, 'availability_status' => 'not_available']);

    expect($budget->fresh()->amount_bob)->toBeNull()
        ->and($budget->revisions()->sole()->previous_amount_bob)->toBe('0.00')
        ->and($budget->revisions()->sole()->new_amount_bob)->toBeNull();
});

it('no crea revisiones duplicadas cuando el monto normalizado y la procedencia son iguales', function (): void {
    $budget = ProjectBudget::factory()->reported('1000.00')->for($this->project)->create();

    $this->budgets->revise($budget, ['amount_bob' => '1000', 'availability_status' => 'reported', 'reason' => 'Sin cambio real']);

    expect($budget->revisions()->exists())->toBeFalse()
        ->and($budget->fresh()->amount_bob)->toBe('1000.00');
});

it('registra cambios reales de procedencia aunque el monto permanezca igual', function (): void {
    $budget = ProjectBudget::factory()->reported('1000.00')->for($this->project)->create();
    $source = InvestmentSource::factory()->create(['institution_id' => $this->project->institution_id]);

    $this->budgets->revise($budget, [
        'amount_bob' => '1000.00', 'availability_status' => 'reported',
        'investment_source_id' => $source->id, 'source_reference' => 'Documento acreditado',
    ]);

    expect($budget->fresh()->investment_source_id)->toBe($source->id)
        ->and($budget->revisions()->sole()->investmentSource->is($source))->toBeTrue()
        ->and($budget->revisions()->sole()->source_reference)->toBe('Documento acreditado');
});

it('rechaza montos ambiguos floats estados y fechas inválidas antes de cambiar el presupuesto', function (array $changes): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();

    expect(fn () => $this->budgets->revise($budget, [
        'amount_bob' => '100.00', 'availability_status' => 'reported', ...$changes,
    ]))->toThrow(ValidationException::class);

    expect($budget->fresh()->amount_bob)->toBeNull()
        ->and($budget->revisions()->exists())->toBeFalse();
})->with([
    [['amount_bob' => -1]],
    [['amount_bob' => 0.1]],
    [['amount_bob' => '-0.01']],
    [['amount_bob' => '0.001']],
    [['amount_bob' => '1e6']],
    [['amount_bob' => 'NaN']],
    [['amount_bob' => '10000000000000000.00']],
    [['amount_bob' => null]],
    [['availability_status' => 'not_available']],
    [['availability_status' => 'unknown']],
    [['effective_date' => '2026-02-30']],
]);

it('exige coherencia institucional de las fuentes al crear y revisar', function (bool $creating): void {
    $foreign = InvestmentSource::factory()->create();
    $budget = ProjectBudget::factory()->for($this->project)->create();
    $data = ['fiscal_year' => 2027, 'amount_bob' => '100.00', 'availability_status' => 'reported', 'investment_source_id' => $foreign->id];

    expect(fn () => $creating ? $this->budgets->create($this->project, $data) : $this->budgets->revise($budget, $data))
        ->toThrow(ValidationException::class);

    expect($this->project->budgets()->count())->toBe(1)
        ->and($budget->revisions()->exists())->toBeFalse();
})->with([false, true]);

it('revierte completamente el historial si falla la inserción o la actualización vigente', function (string $stage): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();
    $event = $stage === 'revision'
        ? 'eloquent.creating: '.ProjectBudgetRevision::class
        : 'eloquent.updating: '.ProjectBudget::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo simulado.');
    });

    try {
        expect(fn () => $this->budgets->revise($budget, ['amount_bob' => '100.00', 'availability_status' => 'reported']))
            ->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect($budget->fresh()->amount_bob)->toBeNull()
        ->and($budget->revisions()->exists())->toBeFalse();
})->with(['revision', 'budget']);
