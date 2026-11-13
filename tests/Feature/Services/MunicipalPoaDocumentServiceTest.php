<?php

use App\Enums\Municipal\PoaDocumentType;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Services\Municipal\MunicipalPoaDocumentService;
use App\Services\Municipal\MunicipalPoaVersionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
    $this->documents = app(MunicipalPoaDocumentService::class);
    $this->poa = MunicipalPoa::factory()->create();
    $this->initialVersion = app(MunicipalPoaVersionService::class)->create($this->poa);
    $this->reformulatedVersion = app(MunicipalPoaVersionService::class)->create($this->poa);

    foreach (['initial', 'reformulated'] as $name) {
        Storage::disk('local')->put("municipal/poas/{$name}.pdf", "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }
});

it('registra un PDF privado inicial y reformulados sin sobrescribir los archivos anteriores', function (): void {
    $path = 'municipal/poas/initial.pdf';
    $originalBytes = Storage::disk('local')->get($path);
    $initial = $this->documents->register($this->initialVersion, ['title' => 'POA inicial', 'pdf_path' => $path]);
    $reformulated = $this->documents->register($this->reformulatedVersion, ['title' => 'Reformulado', 'pdf_path' => 'municipal/poas/reformulated.pdf']);

    expect($initial->version_number)->toBe(1)
        ->and($initial->document_type)->toBe(PoaDocumentType::INITIAL)
        ->and($initial->is_current)->toBeFalse()
        ->and($initial->isPubliclyAvailable())->toBeFalse()
        ->and($initial->file_size_bytes)->toBe(Storage::disk('local')->size($path))
        ->and($initial->mime_type)->toBe('application/pdf')
        ->and($reformulated->version_number)->toBe(2)
        ->and($reformulated->document_type)->toBe(PoaDocumentType::REFORMULATED)
        ->and(Storage::disk('local')->get($path))->toBe($originalBytes)
        ->and($this->poa->documents()->count())->toBe(2);
});

it('publica versiones sin eliminar documentos anteriores y mantiene una sola versión actual', function (): void {
    $initial = $this->documents->register($this->initialVersion, ['title' => 'Inicial', 'pdf_path' => 'municipal/poas/initial.pdf']);
    $reformulated = $this->documents->register($this->reformulatedVersion, ['title' => 'Reformulado', 'pdf_path' => 'municipal/poas/reformulated.pdf']);

    app(MunicipalPoaVersionService::class)->publish($initial->version);
    app(MunicipalPoaVersionService::class)->publish($reformulated->version);

    expect($initial->fresh()->is_current)->toBeFalse()
        ->and($initial->fresh()->isPubliclyAvailable())->toBeTrue()
        ->and($reformulated->fresh()->is_current)->toBeTrue()
        ->and($this->poa->documents()->whereHas('version', fn ($query) => $query->where('is_current', true))->sole()->is($reformulated))->toBeTrue()
        ->and(MunicipalPoaDocument::publiclyAvailable()->count())->toBe(2);
});

it('rechaza reutilizar una ruta histórica y no aumenta el número de versión', function (): void {
    $data = ['title' => 'Inicial', 'pdf_path' => 'municipal/poas/initial.pdf'];
    $this->documents->register($this->initialVersion, $data);

    expect(fn () => $this->documents->register($this->initialVersion, $data))->toThrow(ValidationException::class);
    expect($this->poa->documents()->count())->toBe(1);
});

it('rechaza rutas públicas rutas inseguras archivos ausentes y contenido distinto de PDF', function (string $path): void {
    Storage::disk('local')->put('municipal/poas/not_pdf.pdf', 'No es un PDF.');

    expect(fn () => $this->documents->register($this->initialVersion, ['title' => 'Documento', 'pdf_path' => $path]))
        ->toThrow(ValidationException::class);

    expect($this->poa->documents()->exists())->toBeFalse();
})->with([
    'https://example.com/document.pdf',
    'public/document.pdf',
    'municipal/poas/../initial.pdf',
    'municipal/poas/missing.pdf',
    'municipal/poas/not_pdf.pdf',
]);

it('revierte el cambio de versión actual si falla la publicación del documento nuevo', function (): void {
    $initial = $this->documents->register($this->initialVersion, ['title' => 'Inicial', 'pdf_path' => 'municipal/poas/initial.pdf']);
    $reformulated = $this->documents->register($this->reformulatedVersion, ['title' => 'Reformulado', 'pdf_path' => 'municipal/poas/reformulated.pdf']);
    app(MunicipalPoaVersionService::class)->publish($initial->version);
    $event = 'eloquent.updating: '.MunicipalPoaVersion::class;

    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo simulado.');
    });

    try {
        expect(fn () => app(MunicipalPoaVersionService::class)->publish($reformulated->version))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }

    expect($initial->fresh()->is_current)->toBeTrue()
        ->and($reformulated->fresh()->published_at)->toBeNull()
        ->and($this->poa->documents()->count())->toBe(2);
});
