<?php

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalAuthorityPhotoService;
use App\Services\Municipal\MunicipalPoaDocumentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->municipality = Municipality::factory()->create();
    $this->url = '/api/public/municipalities/'.$this->municipality->id;
});

it('consulta fichas y navegación territorial con filtros reales y paginación', function (): void {
    $this->getJson($this->url)->assertOk()->assertJsonPath('data.id', $this->municipality->id)
        ->assertJsonPath('data.region.id', $this->municipality->region_id)
        ->assertJsonPath('data.province.id', $this->municipality->province_id);
    $this->getJson('/api/public/municipalities/999999999')->assertNotFound();
    $this->getJson('/api/public/regions?search='.$this->municipality->region->name)->assertOk()
        ->assertJsonPath('data.0.id', $this->municipality->region_id);
    $this->getJson('/api/public/provinces?region_id='.$this->municipality->region_id)->assertOk()
        ->assertJsonPath('data.0.id', $this->municipality->province_id);
    $this->getJson('/api/public/municipalities?'.http_build_query([
        'region_id' => $this->municipality->region_id, 'province_id' => $this->municipality->province_id, 'per_page' => 1,
    ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/public/municipalities?per_page=51')->assertUnprocessable();
    $this->getJson('/api/public/provinces?region_id=999999999')->assertUnprocessable();
});

it('oculta municipios y contenido con padres inactivos o eliminados', function (string $parent, bool $deleted): void {
    MunicipalAuthority::factory()->for($this->municipality)->published()->create();
    $record = match ($parent) {
        'municipality' => $this->municipality, 'region' => $this->municipality->region, 'province' => $this->municipality->province,
    };
    $deleted ? $record->delete() : $record->update(['active' => false]);
    $this->getJson($this->url)->assertNotFound();
    $this->getJson($this->url.'/authorities')->assertNotFound();
    $this->getJson($this->url.'/poas')->assertNotFound();
    $this->getJson('/api/public/municipalities')->assertOk()->assertJsonCount(0, 'data');
})->with(['municipality', 'region', 'province'])->with([false, true]);

it('publica autoridades independientemente de su cargo actual sin exponer notas o rutas', function (): void {
    $current = MunicipalAuthority::factory()->for($this->municipality)->mayor()->current()->published()->create(['notes' => 'Privado']);
    $historical = MunicipalAuthority::factory()->for($this->municipality)->published()->create(['source_reference' => 'Privado']);
    $private = MunicipalAuthority::factory()->for($this->municipality)->create();
    $other = MunicipalAuthority::factory()->published()->create();
    $this->getJson($this->url.'/authorities')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $current->id)->assertJsonMissingPath('data.0.notes')
        ->assertJsonMissingPath('data.0.photo_path')->assertJsonMissingPath('data.0.source_reference');
    $this->getJson($this->url.'/authorities?is_current=false')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $historical->id);
    $this->getJson($this->url.'/authorities?role=mayor')->assertOk()->assertJsonPath('data.0.id', $current->id);
    $this->getJson($this->url.'/authorities/'.$historical->id)->assertOk();
    foreach ([$private, $other] as $authority) {
        $this->getJson($this->url.'/authorities/'.$authority->id)->assertNotFound();
        $this->getJson($this->url.'/authorities/'.$authority->id.'/photo')->assertNotFound();
    }
    $this->getJson($this->url.'/authorities?role=invalid')->assertUnprocessable();
    $this->postJson($this->url.'/authorities', [])->assertMethodNotAllowed();
});

it('sirve fotografías autorizadas con validación del almacenamiento privado', function (): void {
    $authority = MunicipalAuthority::factory()->for($this->municipality)->published()->create();
    app(MunicipalAuthorityPhotoService::class)->store($authority, UploadedFile::fake()->image('portrait.jpg'));
    $path = $authority->fresh()->photo_path;
    $bytes = Storage::disk('local')->get($path);
    $url = $this->url.'/authorities/'.$authority->id.'/photo';
    $this->getJson($this->url.'/authorities/'.$authority->id)->assertOk()->assertJsonPath('data.has_photo', true)
        ->assertJsonPath('data.photo_url', $url)->assertJsonMissingPath('data.photo_path');
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($bytes);
    $authority->update(['is_published' => false]);
    $this->mock(MunicipalAuthorityPhotoService::class)->shouldNotReceive('readablePath');
    $this->getJson($url)->assertNotFound();
});

it('consulta PDF inicial reformulado e histórico publicados y oculta borradores futuros', function (): void {
    $poa = MunicipalPoa::factory()->for($this->municipality)->create(['fiscal_year' => 2027, 'notes' => 'Interno']);
    $old = MunicipalPoaDocument::factory()->for($poa)->published()->create(['notes' => 'Interno']);
    $current = MunicipalPoaDocument::factory()->for($poa)->current()->create(['version_number' => 2, 'document_type' => 'reformulated']);
    $draft = MunicipalPoaDocument::factory()->for($poa)->create(['version_number' => 3, 'document_type' => 'reformulated']);
    $future = MunicipalPoaDocument::factory()->for($poa)->create([
        'version_number' => 4, 'document_type' => 'reformulated', 'published_at' => now()->addDay(),
    ]);
    $privatePoa = MunicipalPoa::factory()->for($this->municipality)->create(['fiscal_year' => 2028]);
    MunicipalPoaDocument::factory()->for($privatePoa)->create();
    $url = $this->url.'/poas/'.$poa->id.'/documents';
    $this->getJson($this->url.'/poas?fiscal_year=2027')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.published_documents_count', 2)->assertJsonMissingPath('data.0.notes');
    $this->getJson($this->url.'/poas/'.$privatePoa->id)->assertNotFound();
    $this->getJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $old->id)
        ->assertJsonPath('data.0.download_url', $url.'/'.$old->id.'/file')
        ->assertJsonMissingPath('data.0.pdf_path')->assertJsonMissingPath('data.0.notes')
        ->assertJsonMissingPath('data.0.original_filename');
    $this->getJson($url.'?is_current=true&document_type=reformulated')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $current->id);
    foreach ([$old, $current] as $document) {
        $bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        Storage::disk('local')->put($document->pdf_path, $bytes);
        $this->getJson($url.'/'.$document->id)->assertOk();
        $this->get($url.'/'.$document->id.'/file')->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($bytes);
    }
    $this->mock(MunicipalPoaDocumentService::class)->shouldNotReceive('assertReadable');
    foreach ([$draft, $future] as $document) {
        $this->getJson($url.'/'.$document->id)->assertNotFound();
        $this->getJson($url.'/'.$document->id.'/file')->assertNotFound();
    }
});

it('impide cruces de municipios POA y documentos aun cuando todos estén publicados', function (): void {
    $poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $document = MunicipalPoaDocument::factory()->for($poa)->published()->create();
    $foreign = MunicipalPoaDocument::factory()->published()->create();
    $otherMunicipality = Municipality::factory()->create();
    $this->getJson('/api/public/municipalities/'.$otherMunicipality->id.'/poas/'.$poa->id)->assertNotFound();
    $this->getJson($this->url.'/poas/'.$poa->id.'/documents/'.$foreign->id.'/file')->assertNotFound();
    $this->getJson('/api/public/municipalities/'.$otherMunicipality->id.'/poas/'.$poa->id.'/documents/'.$document->id.'/file')->assertNotFound();
});

it('rechaza PDF publicado ausente corrupto o con extensión inválida', function (string $case): void {
    $document = MunicipalPoaDocument::factory()->published()->create([
        'pdf_path' => $case === 'extension' ? 'municipal/poas/internal.txt' : 'municipal/poas/test.pdf',
    ]);
    if ($case !== 'missing') {
        Storage::disk('local')->put($document->pdf_path, $case === 'extension' ? '%PDF-1.4' : 'No es PDF');
    }
    $poa = $document->municipalPoa;
    $url = '/api/public/municipalities/'.$poa->municipality_id.'/poas/'.$poa->id.'/documents/'.$document->id.'/file';
    $this->getJson($url)->assertNotFound();
})->with(['missing', 'corrupt', 'extension']);

it('limita consultas municipales y protege parámetros', function (): void {
    $this->getJson($this->url.'/poas?fiscal_year=1800')->assertUnprocessable();
    $this->getJson($this->url.'/authorities?is_current=invalid')->assertUnprocessable();
    RateLimiter::for('public-api', fn (): Limit => Limit::perMinute(2)->by('municipal-portal-test'));
    $this->getJson($this->url)->assertOk();
    $this->getJson($this->url)->assertOk();
    $this->getJson($this->url)->assertTooManyRequests();
});
