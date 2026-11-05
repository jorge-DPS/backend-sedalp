<?php

use App\Enums\Investment\BudgetAvailabilityStatus;
use App\Models\Investment\Institution;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\Investment\ProjectBudgetRevision;
use App\Services\Investment\ProjectBudgetService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->service = app(ProjectBudgetService::class);
    $this->project = Project::factory()->create(['total_cost_bob' => '9000000.00']);
    $this->sources = InvestmentSource::factory()->count(2)->create(['institution_id' => $this->project->institution_id]);
    $this->budget = ProjectBudget::factory()->reported('1000000.00')->for($this->project)->create([
        'investment_source_id' => $this->sources[0]->id,
        'source_reference' => 'Documento A',
        'funding_source' => 'Recursos propios',
    ]);
});

it('conserva todos los valores anteriores y nuevos al cambiar un presupuesto', function (string $change): void {
    $changes = match ($change) {
        'amount' => ['amount_bob' => '1500000.00'],
        'document' => ['investment_source_id' => $this->sources[1]->id, 'source_reference' => 'Documento B'],
        'source' => ['investment_source_id' => $this->sources[1]->id],
        'reference' => ['source_reference' => 'Anexo A'],
        'funding' => ['funding_source' => 'Financiamiento externo'],
        'clear' => ['investment_source_id' => null, 'source_reference' => null, 'funding_source' => null],
        'all' => ['amount_bob' => '1500000.00', 'investment_source_id' => $this->sources[1]->id, 'source_reference' => 'Documento B', 'funding_source' => 'Financiamiento externo'],
    };
    $expected = array_replace([
        'amount_bob' => '1000000.00', 'investment_source_id' => $this->sources[0]->id,
        'source_reference' => 'Documento A', 'funding_source' => 'Recursos propios',
    ], $changes);

    $current = $this->service->revise($this->budget, [
        'amount_bob' => '1000000', 'availability_status' => 'reported', ...$changes,
    ]);
    $revision = $current->revisions()->sole();

    expect($revision->previous_amount_bob)->toBe('1000000.00')
        ->and($revision->new_amount_bob)->toBe($expected['amount_bob'])
        ->and($revision->previous_availability_status)->toBe(BudgetAvailabilityStatus::REPORTED)
        ->and($revision->new_availability_status)->toBe(BudgetAvailabilityStatus::REPORTED)
        ->and($revision->previous_investment_source_id)->toBe($this->sources[0]->id)
        ->and($revision->previous_source_reference)->toBe('Documento A')
        ->and($revision->previous_funding_source)->toBe('Recursos propios')
        ->and($revision->investment_source_id)->toBe($expected['investment_source_id'])
        ->and($revision->source_reference)->toBe($expected['source_reference'])
        ->and($revision->new_funding_source)->toBe($expected['funding_source'])
        ->and($revision->previousInvestmentSource->is($this->sources[0]))->toBeTrue()
        ->and($this->sources[0]->previousBudgetRevisions()->sole()->is($revision))->toBeTrue()
        ->and($current->only(array_keys($expected)))->toBe($expected)
        ->and($this->project->budgets()->count())->toBe(1)
        ->and($this->project->budgets()->sum('amount_bob'))->toBe($expected['amount_bob'])
        ->and($this->project->fresh()->total_cost_bob)->toBe('9000000.00');

    if ($expected['investment_source_id'] !== null) {
        expect($revision->investmentSource->institution_id)->toBe($this->project->institution_id);
    }

    $this->service->revise($this->budget, [...$expected, 'availability_status' => 'reported']);

    expect($current->revisions()->count())->toBe(1);

    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
})->with(['amount', 'document', 'source', 'reference', 'funding', 'clear', 'all']);

it('registra financiamiento distinto con Bs 800000 y omite actualizaciones idénticas', function (): void {
    $budget = ProjectBudget::factory()->reported('800000.00')->for($this->project)->create([
        'fiscal_year' => 2027, 'funding_source' => 'Recursos propios',
    ]);

    $current = $this->service->revise($budget, [
        'amount_bob' => '800000', 'availability_status' => 'reported', 'funding_source' => 'Financiamiento externo',
    ]);
    $before = $current->revisions()->sole()->getAttributes();

    $this->service->revise($budget, [
        'amount_bob' => '800000.0', 'availability_status' => 'reported',
        'investment_source_id' => null, 'source_reference' => null, 'funding_source' => 'Financiamiento externo',
    ]);

    expect($current->revisions()->sole()->getAttributes())->toBe($before)
        ->and($before['previous_funding_source'])->toBe('Recursos propios')
        ->and($before['new_funding_source'])->toBe('Financiamiento externo')
        ->and($current->fresh()->amount_bob)->toBe('800000.00');
});

it('rechaza cambios directos de financiamiento sin revisión incluso al borrarlo', function (bool $queryBuilder, ?string $funding): void {
    expect(fn () => DB::transaction(fn () => $queryBuilder
        ? DB::table('project_budgets')->where('id', $this->budget->id)->update(['funding_source' => $funding])
        : $this->budget->update(['funding_source' => $funding])))
        ->toThrow(QueryException::class);

    expect($this->budget->fresh()->funding_source)->toBe('Recursos propios')
        ->and($this->budget->revisions()->exists())->toBeFalse();
})->with([false, true])->with(['Financiamiento externo', null]);

it('rechaza valores anteriores falsificados en revisiones directas', function (string $field, mixed $value): void {
    $attributes = ProjectBudgetRevision::factory()->for($this->budget)->make([$field => $value])->getAttributes();

    expect(fn () => DB::transaction(fn () => DB::table('project_budget_revisions')->insert($attributes)))
        ->toThrow(QueryException::class);

    expect($this->budget->revisions()->exists())->toBeFalse();
})->with([
    ['previous_investment_source_id', null],
    ['previous_investment_source_id', 999999999],
    ['previous_source_reference', null],
    ['previous_source_reference', 'Documento falso'],
    ['previous_funding_source', null],
    ['previous_funding_source', 'Financiamiento falso'],
    ['revision_number', 2],
]);

it('rechaza revisiones sin cambio real también cuando el financiamiento está informado', function (): void {
    expect(fn () => DB::transaction(fn () => ProjectBudgetRevision::factory()->for($this->budget)->create([
        'new_amount_bob' => $this->budget->amount_bob,
        'investment_source_id' => $this->budget->investment_source_id,
        'source_reference' => $this->budget->source_reference,
        'new_funding_source' => $this->budget->funding_source,
    ])))->toThrow(QueryException::class);
});

it('comprueba el financiamiento nuevo al actualizar y al confirmar la transacción', function (string $stage): void {
    $attributes = ProjectBudgetRevision::factory()->for($this->budget)->make([
        'new_amount_bob' => '1000000.00',
        'investment_source_id' => $this->budget->investment_source_id,
        'source_reference' => $this->budget->source_reference,
        'new_funding_source' => 'Financiamiento externo',
    ])->getAttributes();

    expect(fn () => DB::transaction(function () use ($attributes, $stage): void {
        DB::table('project_budget_revisions')->insert($attributes);

        if ($stage === 'update') {
            DB::table('project_budgets')->where('id', $this->budget->id)->update(['funding_source' => 'Otro financiamiento']);
        }

        DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    }))->toThrow(QueryException::class);

    expect($this->budget->revisions()->exists())->toBeFalse()
        ->and($this->budget->fresh()->funding_source)->toBe('Recursos propios');
})->with(['update', 'commit']);

it('revierte el historial completo si falla la actualización después de insertar la revisión', function (): void {
    $before = $this->budget->fresh()->getAttributes();
    $event = 'eloquent.updating: '.ProjectBudget::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo posterior a la revisión.');
    });

    try {
        expect(fn () => $this->service->revise($this->budget, [
            'amount_bob' => '1500000.00', 'availability_status' => 'reported',
            'investment_source_id' => $this->sources[1]->id, 'source_reference' => 'Documento B',
            'funding_source' => 'Financiamiento externo',
        ]))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect($this->budget->fresh()->getAttributes())->toBe($before)
        ->and($this->budget->revisions()->exists())->toBeFalse();
});

it('rechaza una segunda revisión mientras la anterior no se haya aplicado al presupuesto', function (?string $funding): void {
    $attributes = ProjectBudgetRevision::factory()->for($this->budget)->make([
        'new_amount_bob' => $this->budget->amount_bob,
        'investment_source_id' => $this->budget->investment_source_id,
        'source_reference' => $this->budget->source_reference,
        'new_funding_source' => $funding,
    ])->getAttributes();

    expect(fn () => DB::transaction(function () use ($attributes): void {
        DB::table('project_budget_revisions')->insert($attributes);
        DB::table('project_budget_revisions')->insert([...$attributes, 'revision_number' => 2]);
        DB::table('project_budgets')->where('id', $this->budget->id)->update([
            'funding_source' => $attributes['new_funding_source'],
        ]);
        DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
    }))->toThrow(QueryException::class);

    expect($this->budget->revisions()->exists())->toBeFalse()
        ->and($this->budget->fresh()->funding_source)->toBe('Recursos propios');
})->with(['Financiamiento externo', null]);

it('encadena revisiones consecutivas con fuentes referencias financiamiento y NULL correctos', function (): void {
    $stale = $this->budget->fresh();
    foreach ([
        ['amount_bob' => '1500000.00', 'availability_status' => 'reported', 'investment_source_id' => $this->sources[1]->id, 'source_reference' => 'Documento B', 'funding_source' => 'Financiamiento externo'],
        ['amount_bob' => null, 'availability_status' => 'not_available', 'investment_source_id' => null, 'source_reference' => null, 'funding_source' => null],
        ['amount_bob' => 0, 'availability_status' => 'reported', 'investment_source_id' => $this->sources[0]->id, 'source_reference' => 'Documento C', 'funding_source' => 'Recursos propios'],
    ] as $changes) {
        $this->service->revise($stale, $changes);
    }
    $history = $this->budget->revisions()->orderBy('revision_number')->get();

    expect($history->pluck('revision_number')->all())->toBe([1, 2, 3]);

    foreach ([1, 2] as $index) {
        expect($history[$index]->previous_amount_bob)->toBe($history[$index - 1]->new_amount_bob)
            ->and($history[$index]->previous_availability_status)->toBe($history[$index - 1]->new_availability_status)
            ->and($history[$index]->previous_investment_source_id)->toBe($history[$index - 1]->investment_source_id)
            ->and($history[$index]->previous_source_reference)->toBe($history[$index - 1]->source_reference)
            ->and($history[$index]->previous_funding_source)->toBe($history[$index - 1]->new_funding_source);
    }

    expect($this->project->budgets()->count())->toBe(1)
        ->and($this->budget->fresh()->amount_bob)->toBe('0.00')
        ->and($this->project->budgets()->sum('amount_bob'))->toBe('0.00');
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
});

it('mantiene la coherencia y el borrado restringido de fuentes que solo aparecen como anteriores', function (string $target): void {
    $this->service->revise($this->budget, [
        'amount_bob' => '1000000.00', 'availability_status' => 'reported',
        'investment_source_id' => null, 'source_reference' => null,
    ]);
    $other = Institution::factory()->create();
    $previous = $this->sources[0];

    expect(fn () => DB::transaction(fn () => match ($target) {
        'source' => $previous->update(['institution_id' => $other->id]),
        'project' => $this->project->update(['institution_id' => $other->id]),
        'delete' => $previous->delete(),
    }))->toThrow(QueryException::class);

    expect($this->budget->revisions()->sole()->previousInvestmentSource->is($previous))->toBeTrue();
})->with(['source', 'project', 'delete']);

it('valida el financiamiento nuevo antes de modificar cualquier dato', function (mixed $funding): void {
    expect(fn () => $this->service->revise($this->budget, [
        'amount_bob' => '1000000.00', 'availability_status' => 'reported', 'funding_source' => $funding,
    ]))->toThrow(ValidationException::class);

    expect($this->budget->fresh()->funding_source)->toBe('Recursos propios')
        ->and($this->budget->revisions()->exists())->toBeFalse();
})->with([str_repeat('a', 256), 123, false]);
