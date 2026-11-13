<?php

use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $this->base = '/api/admin/municipalities/'.$this->municipality->id.'/poas/'.$this->poa->id;
    $this->versionsUrl = $this->base.'/versions';
    $this->bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
    $this->pdf = fn (string $name = 'POA institucional.pdf'): UploadedFile => UploadedFile::fake()->createWithContent($name, $this->bytes);
});

it('adjunta tres PDF a una sola versión inicial con nombres originales e internos propios', function (): void {
    $id = $this->postJson($this->versionsUrl, [])->assertCreated()->assertJsonPath('data.version_number', 1)
        ->assertJsonPath('data.document_type', 'initial')->assertJsonPath('data.is_published', false)->json('data.id');
    foreach (['POA institucional.pdf', 'Presupuesto.pdf', 'Anexos de planificación.pdf'] as $name) {
        $this->postJson($this->versionsUrl.'/'.$id.'/documents', ['pdf' => ($this->pdf)($name), 'title' => $name])
            ->assertCreated()->assertJsonPath('data.municipal_poa_version_id', $id)
            ->assertJsonPath('data.original_filename', $name)->assertJsonMissingPath('data.pdf_path')
            ->assertJsonMissingPath('data.published_at')->assertJsonMissingPath('data.is_current');
    }
    expect($this->poa->versions()->count())->toBe(1)->and($this->poa->documents()->count())->toBe(3)
        ->and($this->poa->documents()->pluck('pdf_path')->unique()->count())->toBe(3);
    $this->getJson($this->versionsUrl.'/'.$id)->assertOk()->assertJsonPath('data.documents_count', 3);
    $this->getJson($this->versionsUrl.'/'.$id.'/documents?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
});

it('publica conjuntamente conserva versiones anteriores e identifica una sola versión vigente', function (): void {
    $this->user->givePermissionTo('municipal.documents.publish');
    $ids = [];
    $paths = [];
    foreach ([1, 2] as $number) {
        $id = $this->postJson($this->versionsUrl, [])->assertCreated()->assertJsonPath('data.version_number', $number)->json('data.id');
        $ids[] = $id;
        foreach (['Principal.pdf', 'Anexo.pdf'] as $name) {
            $this->postJson($this->versionsUrl.'/'.$id.'/documents', ['pdf' => ($this->pdf)($name), 'title' => $name, 'notes' => 'Interno'])->assertCreated();
        }
        $public = str_replace('/admin/', '/public/', $this->versionsUrl.'/'.$id);
        $this->getJson($public)->assertNotFound();
        $this->getJson($public.'/documents')->assertNotFound();
        $this->putJson($this->versionsUrl.'/'.$id.'/publication')->assertOk()->assertJsonPath('data.is_current', true);
        $this->getJson($public)->assertOk()->assertJsonPath('data.documents_count', 2);
        $this->getJson($public.'/documents')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.notes')->assertJsonMissingPath('data.0.pdf_path');
    }
    $initial = MunicipalPoaVersion::findOrFail($ids[0]);
    expect($initial->is_current)->toBeFalse()->and($initial->isPubliclyAvailable())->toBeTrue()
        ->and($this->poa->versions()->where('is_current', true)->sole()->id)->toBe($ids[1]);
    foreach ($this->poa->documents()->get() as $document) {
        $url = str_replace('/admin/', '/public/', $this->versionsUrl.'/'.$document->municipal_poa_version_id.'/documents/'.$document->id.'/file');
        $this->get($url)->assertOk()->assertStreamedContent($this->bytes);
        expect(Storage::disk('local')->get($document->pdf_path))->toBe($this->bytes);
    }
    $this->getJson(str_replace('/admin/', '/public/', $this->versionsUrl).'?is_current=true')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[1]);
});

it('impide anexos y cambios de contenido después de publicar incluso mediante SQL', function (): void {
    $this->user->givePermissionTo('municipal.documents.publish');
    $id = $this->postJson($this->versionsUrl, [])->json('data.id');
    $documentId = $this->postJson($this->versionsUrl.'/'.$id.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Inicial'])->assertCreated()->json('data.id');
    $this->putJson($this->versionsUrl.'/'.$id.'/publication')->assertOk();
    $document = MunicipalPoaDocument::findOrFail($documentId);
    $before = $document->getAttributes();
    $this->postJson($this->versionsUrl.'/'.$id.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Anexo tardío'])->assertUnprocessable();
    expect(fn () => DB::transaction(fn () => MunicipalPoaDocument::factory()->create(['municipal_poa_version_id' => $id])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $document->update(['title' => 'Cambio silencioso'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => $document->delete()))->toThrow(QueryException::class);
    expect($document->fresh()->getAttributes())->toBe($before)->and($this->poa->documents()->count())->toBe(1);
});

it('rechaza versiones vacías archivos corruptos y usuarios sin permiso de publicación', function (): void {
    $id = $this->postJson($this->versionsUrl, [])->json('data.id');
    $this->putJson($this->versionsUrl.'/'.$id.'/publication')->assertForbidden();
    $this->user->givePermissionTo('municipal.documents.publish');
    $this->putJson($this->versionsUrl.'/'.$id.'/publication')->assertUnprocessable();
    $docId = $this->postJson($this->versionsUrl.'/'.$id.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Inicial'])->json('data.id');
    Storage::disk('local')->put(MunicipalPoaDocument::findOrFail($docId)->pdf_path, 'Corrupto');
    $this->putJson($this->versionsUrl.'/'.$id.'/publication')->assertUnprocessable();
    expect(MunicipalPoaVersion::findOrFail($id)->published_at)->toBeNull();
});

it('reserva números y tipos al servidor y protege la unicidad en PostgreSQL', function (): void {
    $this->postJson($this->versionsUrl, ['version_number' => 99])->assertUnprocessable();
    $this->postJson($this->versionsUrl, ['document_type' => 'reformulated'])->assertUnprocessable();
    $first = $this->postJson($this->versionsUrl, [])->assertCreated()->json('data.id');
    $this->postJson($this->versionsUrl, [])->assertCreated()->assertJsonPath('data.version_number', 2)->assertJsonPath('data.document_type', 'reformulated');
    expect(fn () => DB::transaction(fn () => MunicipalPoaVersion::factory()->for($this->poa)->create()))->toThrow(QueryException::class);
});

it('no cruza documentos versiones POA ni municipios mediante rutas anidadas', function (): void {
    $first = $this->postJson($this->versionsUrl, [])->json('data.id');
    $second = $this->postJson($this->versionsUrl, [])->json('data.id');
    $docId = $this->postJson($this->versionsUrl.'/'.$first.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Inicial'])->json('data.id');
    $this->getJson($this->versionsUrl.'/'.$second.'/documents/'.$docId)->assertNotFound();
    $foreign = MunicipalPoaVersion::factory()->create();
    $this->getJson($this->versionsUrl.'/'.$foreign->id)->assertNotFound();
    $this->postJson($this->versionsUrl.'/'.$foreign->id.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Cruce'])->assertNotFound();
    $this->getJson(str_replace('/admin/', '/public/', $this->versionsUrl.'/'.$first.'/documents/'.$docId.'/file'))->assertNotFound();
});

it('mantiene lecturas antiguas pero exige versión explícita y evita publicación parcial por ruta antigua', function (): void {
    $this->postJson($this->base.'/documents', ['pdf' => ($this->pdf)(), 'title' => 'Sin versión'])->assertUnprocessable()->assertJsonValidationErrors('municipal_poa_version_id');
    $id = $this->postJson($this->versionsUrl, [])->json('data.id');
    $docId = null;
    foreach (['Primero', 'Segundo'] as $title) {
        $docId = $this->postJson($this->base.'/documents', [
            'municipal_poa_version_id' => $id, 'pdf' => ($this->pdf)(), 'title' => $title,
        ])->assertCreated()->json('data.id');
    }
    $this->getJson($this->base.'/documents')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson($this->base.'/documents/'.$docId)->assertOk();
    $this->user->givePermissionTo('municipal.documents.publish');
    $this->putJson($this->base.'/documents/'.$docId.'/publication')->assertConflict();
    expect(MunicipalPoaVersion::findOrFail($id)->published_at)->toBeNull();
});
