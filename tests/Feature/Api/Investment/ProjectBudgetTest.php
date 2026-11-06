<?php

use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\User;
use App\Services\Investment\ProjectBudgetService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->project = Project::factory()->create(['total_cost_bob' => '9000000.00']);
    $this->sources = InvestmentSource::factory()->count(2)->create([
        'institution_id' => $this->project->institution_id, 'file_path' => 'private/secret.pdf',
    ]);
    $this->url = "/api/admin/investment/projects/{$this->project->id}/budgets";
    $this->payload = [
        'fiscal_year' => 2027, 'amount_bob' => '1000000.00', 'availability_status' => 'reported',
        'investment_source_id' => $this->sources[0]->id, 'source_reference' => 'Documento A', 'funding_source' => 'Recursos propios',
    ];
});

it('registra consulta y pagina presupuestos anuales sin alterar el costo total', function (): void {
    $id = $this->postJson($this->url, $this->payload)->assertCreated()
        ->assertJsonPath('data.amount_bob', '1000000.00')->assertJsonPath('data.project_id', $this->project->id)
        ->assertJsonMissingPath('data.investment_source.file_path')->json('data.id');
    $this->postJson($this->url, [...$this->payload, 'fiscal_year' => 2028, 'amount_bob' => null, 'availability_status' => 'not_available'])
        ->assertCreated()->assertJsonPath('data.amount_bob', null);
    $this->postJson($this->url, [...$this->payload, 'fiscal_year' => 2029, 'amount_bob' => 0])
        ->assertCreated()->assertJsonPath('data.amount_bob', '0.00');

    $this->getJson($this->url.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.fiscal_year', 2029);
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.revisions_count', 0);
    expect($this->project->fresh()->total_cost_bob)->toBe('9000000.00');
});

it('rechaza presupuestos repetidos por proyecto y gestión', function (): void {
    $this->postJson($this->url, $this->payload)->assertCreated();
    $this->postJson($this->url, $this->payload)->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
    expect($this->project->budgets()->count())->toBe(1);
});

it('valida importes sin floats y mantiene la diferencia entre NULL y cero', function (array $changes, string $field): void {
    $this->postJson($this->url, [...$this->payload, ...$changes])->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->project->budgets()->exists())->toBeFalse();
})->with([
    [['amount_bob' => 100.25], 'amount_bob'], [['amount_bob' => '-0.01'], 'amount_bob'],
    [['amount_bob' => '0.001'], 'amount_bob'], [['amount_bob' => 'NaN'], 'amount_bob'],
    [['amount_bob' => '1e6'], 'amount_bob'], [['amount_bob' => '10000000000000000.00'], 'amount_bob'],
    [['amount_bob' => null], 'amount_bob'], [['availability_status' => 'not_available'], 'amount_bob'],
    [['availability_status' => 'unknown'], 'availability_status'], [['fiscal_year' => 1800], 'fiscal_year'],
    [['investment_source_id' => 999999999], 'investment_source_id'],
]);

it('conserva valores anteriores y nuevos de monto documento y financiamiento sin duplicar el vigente', function (): void {
    $id = $this->postJson($this->url, $this->payload)->assertCreated()->json('data.id');
    $this->postJson($this->url."/{$id}/revisions", [
        'amount_bob' => '1500000.00', 'availability_status' => 'reported',
        'investment_source_id' => $this->sources[1]->id, 'source_reference' => 'Documento B',
        'funding_source' => 'Financiamiento externo', 'reason' => 'Rectificación', 'effective_date' => '2027-01-01',
        'previous_amount_bob' => '999.00', 'revision_number' => 99,
    ])->assertOk()->assertJsonPath('data.amount_bob', '1500000.00')->assertJsonPath('data.revisions_count', 1);
    $this->getJson($this->url."/{$id}/revisions")->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.revision_number', 1)->assertJsonPath('data.0.previous_amount_bob', '1000000.00')
        ->assertJsonPath('data.0.new_amount_bob', '1500000.00')
        ->assertJsonPath('data.0.previous_investment_source_id', $this->sources[0]->id)
        ->assertJsonPath('data.0.investment_source_id', $this->sources[1]->id)
        ->assertJsonPath('data.0.previous_source_reference', 'Documento A')->assertJsonPath('data.0.source_reference', 'Documento B')
        ->assertJsonPath('data.0.previous_funding_source', 'Recursos propios')->assertJsonPath('data.0.new_funding_source', 'Financiamiento externo')
        ->assertJsonMissingPath('data.0.previous_investment_source.file_path')->assertJsonMissingPath('data.0.investment_source.file_path');
    expect($this->project->budgets()->count())->toBe(1)
        ->and($this->project->budgets()->sum('amount_bob'))->toBe('1500000.00');
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
});

it('registra cambios únicamente documentales o de financiamiento y omite solicitudes sin cambios', function (string $field): void {
    $id = $this->postJson($this->url, $this->payload)->assertCreated()->json('data.id');
    $changes = $field === 'funding_source' ? ['funding_source' => 'Financiamiento externo'] : ['source_reference' => 'Documento A corregido'];
    $payload = ['amount_bob' => '1000000', 'availability_status' => 'reported', ...$changes];
    $this->postJson($this->url."/{$id}/revisions", $payload)->assertOk()->assertJsonPath('data.revisions_count', 1);
    $this->postJson($this->url."/{$id}/revisions", $payload)->assertOk()->assertJsonPath('data.revisions_count', 1);
    expect(ProjectBudget::findOrFail($id)->amount_bob)->toBe('1000000.00');
})->with(['funding_source', 'source_reference']);

it('encadena y pagina el historial por número de revisión', function (): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();
    foreach (['100.00', '200.00', '300.00'] as $amount) {
        $this->postJson($this->url."/{$budget->id}/revisions", ['amount_bob' => $amount, 'availability_status' => 'reported'])->assertOk();
    }
    $this->getJson($this->url."/{$budget->id}/revisions?per_page=2")->assertOk()->assertJsonPath('meta.total', 3)
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.revision_number', 1)->assertJsonPath('data.1.previous_amount_bob', '100.00');
    $this->getJson($this->url."/{$budget->id}/revisions?per_page=2&page=2")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.revision_number', 3);
    DB::statement('SET CONSTRAINTS project_budget_revision_applied_check IMMEDIATE');
});

it('rechaza presupuestos de otro proyecto en todas las rutas anidadas', function (string $method, string $suffix): void {
    $foreign = ProjectBudget::factory()->create();
    $this->json($method, $this->url."/{$foreign->id}".$suffix, ['amount_bob' => '100.00', 'availability_status' => 'reported'])->assertNotFound();
    expect($foreign->revisions()->exists())->toBeFalse();
})->with([['GET', ''], ['GET', '/revisions'], ['POST', '/revisions']]);

it('exige fuentes institucionalmente coherentes al crear y revisar presupuestos', function (bool $revision): void {
    $foreign = InvestmentSource::factory()->create();
    $url = $this->url;
    if ($revision) {
        $budget = ProjectBudget::factory()->for($this->project)->create();
        $url .= "/{$budget->id}/revisions";
    }
    $this->postJson($url, [...$this->payload, 'investment_source_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('investment_source_id');
    expect($this->project->budgets()->count())->toBe($revision ? 1 : 0);
})->with([false, true]);

it('mantiene inmutable el historial y no ofrece actualización directa del presupuesto', function (): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();
    app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '100.00', 'availability_status' => 'reported']);
    $revision = $budget->revisions()->sole();
    $this->patchJson($this->url."/{$budget->id}", ['amount_bob' => '200.00'])->assertMethodNotAllowed();
    $this->deleteJson($this->url."/{$budget->id}")->assertMethodNotAllowed();
    $this->patchJson($this->url."/{$budget->id}/revisions/{$revision->id}", ['new_amount_bob' => '200.00'])->assertNotFound();
    $this->deleteJson($this->url."/{$budget->id}/revisions/{$revision->id}")->assertNotFound();
    expect($budget->fresh()->amount_bob)->toBe('100.00')
        ->and($revision->fresh()->new_amount_bob)->toBe('100.00');
});

it('revierte la revisión completa si falla la actualización del vigente', function (): void {
    $budget = ProjectBudget::factory()->for($this->project)->reported('100.00')->create(['funding_source' => 'Recursos propios']);
    $event = 'eloquent.updating: '.ProjectBudget::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo posterior a insertar la revisión.');
    });

    try {
        $this->postJson($this->url."/{$budget->id}/revisions", [
            'amount_bob' => '200.00', 'availability_status' => 'reported', 'funding_source' => 'Financiamiento externo',
        ])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }

    expect($budget->fresh()->amount_bob)->toBe('100.00')
        ->and($budget->fresh()->funding_source)->toBe('Recursos propios')
        ->and($budget->revisions()->exists())->toBeFalse();
});

it('valida la paginación de presupuestos e historial', function (): void {
    $budget = ProjectBudget::factory()->for($this->project)->create();
    $this->getJson($this->url.'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson($this->url."/{$budget->id}/revisions?page=0")->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('conserva todos los dígitos monetarios en JSON al crear revisar y consultar', function (): void {
    $id = $this->postJson($this->url, [...$this->payload, 'amount_bob' => '9999999999999999.99'])
        ->assertCreated()->assertJsonPath('data.amount_bob', '9999999999999999.99')->json('data.id');
    $this->postJson($this->url."/{$id}/revisions", ['amount_bob' => '0.00', 'availability_status' => 'reported'])
        ->assertOk()->assertJsonPath('data.amount_bob', '0.00');
    $this->getJson($this->url."/{$id}/revisions")->assertOk()
        ->assertJsonPath('data.0.previous_amount_bob', '9999999999999999.99')->assertJsonPath('data.0.new_amount_bob', '0.00');
});
