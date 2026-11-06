<?php

use App\Models\Investment\Institution;
use App\Models\Investment\InvestmentSource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Models\User;
use App\Services\Investment\ProjectBudgetService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api');
    $this->institution = Institution::factory()->create();
    $this->payload = [
        'institution_id' => $this->institution->id, 'source_type' => 'institutional_report',
        'title' => 'Informe de inversión', 'provided_by_name' => 'Entidad proveedora',
        'fiscal_year' => 2027, 'document_reference' => 'INF-2027-001',
        'version_label' => 'v1', 'received_at' => '2026-10-01',
    ];
});

it('crea consulta y actualiza metadatos sin convertir al proveedor en propietario', function (): void {
    $id = $this->postJson('/api/admin/investment/sources', $this->payload)
        ->assertCreated()->assertJsonPath('data.institution.id', $this->institution->id)
        ->assertJsonPath('data.provided_by_name', 'Entidad proveedora')->assertJsonMissingPath('data.file_path')->json('data.id');

    $this->getJson("/api/admin/investment/sources/{$id}")->assertOk()->assertJsonPath('data.document_reference', 'INF-2027-001');
    $this->patchJson("/api/admin/investment/sources/{$id}", ['title' => 'Informe rectificado', 'version_label' => 'v2'])
        ->assertOk()->assertJsonPath('data.title', 'Informe rectificado')
        ->assertJsonPath('data.institution_id', $this->institution->id)->assertJsonPath('data.source_type', 'institutional_report');
    $this->putJson("/api/admin/investment/sources/{$id}", ['notes' => 'Metadatos revisados'])->assertOk();
    $other = Institution::factory()->create();
    $this->patchJson("/api/admin/investment/sources/{$id}", ['institution_id' => $other->id])
        ->assertOk()->assertJsonPath('data.institution_id', $other->id);
});

it('filtra pagina y ordena fuentes sin exponer rutas privadas', function (): void {
    $sources = InvestmentSource::factory()->count(3)->create([
        'institution_id' => $this->institution->id, 'fiscal_year' => 2027,
        'title' => 'Informe 2027', 'file_path' => 'investment/private/confidential.pdf',
    ]);
    InvestmentSource::factory()->create(['fiscal_year' => 2026]);
    $query = http_build_query(['institution_id' => $this->institution->id, 'fiscal_year' => 2027, 'search' => 'informe', 'per_page' => 2, 'sort_by' => 'id', 'sort_direction' => 'asc']);
    $this->getJson('/api/admin/investment/sources?'.$query)->assertOk()
        ->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $sources[0]->id)
        ->assertJsonMissingPath('data.0.file_path')->assertDontSee('confidential.pdf');
    $this->getJson("/api/admin/investment/sources/{$sources[0]->id}")->assertOk()->assertJsonMissingPath('data.file_path')
        ->assertDontSee('confidential.pdf');
    $this->getJson("/api/admin/investment/sources/{$sources[0]->id}/download")->assertNotFound();
    $this->deleteJson("/api/admin/investment/sources/{$sources[0]->id}")->assertMethodNotAllowed();
});

it('valida metadatos e impide asignar rutas de archivos mediante la API', function (array $changes, string $field): void {
    $this->postJson('/api/admin/investment/sources', [...$this->payload, ...$changes])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(InvestmentSource::count())->toBe(0);
})->with([
    [['institution_id' => 999999999], 'institution_id'], [['source_type' => null], 'source_type'],
    [['title' => null], 'title'], [['fiscal_year' => 2101], 'fiscal_year'],
    [['received_at' => '2026-02-30'], 'received_at'], [['source_url' => 'file:///private/document.pdf'], 'source_url'],
    [['file_path' => 'private/secret.pdf'], 'file_path'], [['file' => 'fake.pdf'], 'file'],
]);

it('rechaza filtros y ordenamientos inválidos de fuentes', function (array $query, string $field): void {
    $this->getJson('/api/admin/investment/sources?'.http_build_query($query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['per_page' => 0], 'per_page'], [['page' => -1], 'page'],
    [['sort_by' => 'file_path'], 'sort_by'], [['sort_direction' => 'DESC; SELECT 1'], 'sort_direction'],
    [['institution_id' => 999999999], 'institution_id'], [['fiscal_year' => 1800], 'fiscal_year'],
]);

it('conserva la institución de fuentes vinculadas a proyectos actuales eliminados o historiales', function (string $link): void {
    $source = InvestmentSource::factory()->create(['institution_id' => $this->institution->id]);
    $other = Institution::factory()->create();
    $project = Project::factory()->for($this->institution)->create();

    if (in_array($link, ['primary', 'deleted'], true)) {
        $project->update(['primary_investment_source_id' => $source->id]);
        if ($link === 'deleted') {
            $project->delete();
        }
    } else {
        $budget = ProjectBudget::factory()->for($project)->reported('100.00')->create(['investment_source_id' => $source->id]);
        if ($link === 'history') {
            app(ProjectBudgetService::class)->revise($budget, ['amount_bob' => '100.00', 'availability_status' => 'reported', 'investment_source_id' => null]);
        }
    }

    $this->patchJson("/api/admin/investment/sources/{$source->id}", ['institution_id' => $other->id, 'title' => 'Cambio inválido'])
        ->assertUnprocessable()->assertJsonValidationErrors('institution_id');

    expect($source->fresh()->institution_id)->toBe($this->institution->id)
        ->and($source->fresh()->title)->toBe($source->title);
})->with(['primary', 'deleted', 'budget', 'history']);

it('no permite instituciones desactivadas al registrar fuentes', function (): void {
    $this->institution->update(['active' => false]);
    $this->postJson('/api/admin/investment/sources', $this->payload)->assertUnprocessable()->assertJsonValidationErrors('institution_id');
});
