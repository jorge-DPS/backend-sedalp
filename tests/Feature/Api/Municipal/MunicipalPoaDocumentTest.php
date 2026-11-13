<?php

use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Models\User;
use App\Services\Municipal\MunicipalPoaVersionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->poa = MunicipalPoa::factory()->for($this->municipality)->create();
    $this->version = app(MunicipalPoaVersionService::class)->create($this->poa);
    $this->url = "/api/admin/municipalities/{$this->municipality->id}/poas/{$this->poa->id}/documents";
    $this->pdf = fn (string $name = 'document.pdf'): UploadedFile => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
});

it('carga PDF inicial y reformulados en privado con versiones correlativas y archivos propios', function (): void {
    $id = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Inicial consolidado', 'pdf' => ($this->pdf)()])
        ->assertCreated()->assertJsonPath('data.version_number', 1)->assertJsonPath('data.document_type', 'initial')
        ->assertJsonPath('data.is_current', false)->assertJsonPath('data.is_published', false)
        ->assertJsonMissingPath('data.pdf_path')->assertJsonMissingPath('data.url')->json('data.id');
    $initial = MunicipalPoaDocument::findOrFail($id);
    $bytes = Storage::disk('local')->get($initial->pdf_path);
    $this->version = app(MunicipalPoaVersionService::class)->create($this->poa);
    $secondId = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Reformulado', 'pdf' => ($this->pdf)('document.pdf')])
        ->assertCreated()->assertJsonPath('data.version_number', 2)->assertJsonPath('data.document_type', 'reformulated')->json('data.id');
    $second = MunicipalPoaDocument::findOrFail($secondId);

    expect($initial->pdf_path)->toStartWith("municipal/poas/{$this->poa->id}/")
        ->and($second->pdf_path)->not->toBe($initial->pdf_path)
        ->and(Storage::disk('local')->get($initial->pdf_path))->toBe($bytes)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    $this->getJson($this->url.'?per_page=1&document_type=reformulated')->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $secondId);
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonMissingPath('data.pdf_path');
    $this->get($this->url."/{$id}/file")->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($bytes);
    $this->patchJson($this->url.'/'.$id, ['pdf_path' => $second->pdf_path])->assertMethodNotAllowed();
    $this->deleteJson($this->url.'/'.$id)->assertMethodNotAllowed();
});

it('rechaza archivos inválidos o extensiones que no corresponden a PDF', function (string $case): void {
    $file = match ($case) {
        'text' => UploadedFile::fake()->createWithContent('false.pdf', 'Esto no es PDF'),
        'extension' => ($this->pdf)('document.txt'),
        'image' => UploadedFile::fake()->image('image.png'),
    };
    $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Intento', 'pdf' => $file])->assertUnprocessable()->assertJsonValidationErrors('pdf');
    expect($this->poa->documents()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['text', 'extension', 'image']);

it('aplica el límite configurable de tamaño de PDF', function (): void {
    config(['municipal.poa_max_upload_kb' => 1]);
    $file = UploadedFile::fake()->createWithContent('large.pdf', "%PDF-1.4\n".str_repeat('a', 2048));
    $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Grande', 'pdf' => $file])->assertUnprocessable()->assertJsonValidationErrors('pdf');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('rechaza atributos controlados por el servidor incluso si son nulos', function (string $field): void {
    $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Inicial', 'pdf' => ($this->pdf)(), $field => null])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->poa->documents()->exists())->toBeFalse();
})->with(['version_number', 'document_type', 'pdf_path', 'is_current', 'published_at', 'municipal_poa_id', 'original_filename']);

it('publica solo con permiso específico y mantiene un único documento actual', function (): void {
    $ids = [];
    foreach (['Inicial', 'Reformulado'] as $title) {
        if ($title === 'Reformulado') {
            $this->version = app(MunicipalPoaVersionService::class)->create($this->poa);
        }
        $ids[] = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => $title, 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    }
    $this->putJson($this->url."/{$ids[0]}/publication")->assertForbidden();
    $reviewer = User::factory()->create();
    $reviewer->assignRole('responsable');
    $this->actingAs($reviewer, 'api')->putJson($this->url."/{$ids[0]}/publication")->assertOk()->assertJsonPath('data.is_current', true);
    $this->putJson($this->url."/{$ids[1]}/publication")->assertOk()->assertJsonPath('data.is_current', true);
    $this->getJson($this->url.'?is_current=true&published=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids[1]);

    expect($this->poa->versions()->where('is_current', true)->count())->toBe(1)
        ->and($this->poa->documents()->publiclyAvailable()->count())->toBe(2)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('no publica archivos ausentes o corruptos y conserva la versión anterior', function (bool $missing): void {
    $this->user->givePermissionTo('municipal.documents.publish');
    $firstId = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Inicial', 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    $this->version = app(MunicipalPoaVersionService::class)->create($this->poa);
    $nextId = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Nuevo', 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    $this->putJson($this->url."/{$firstId}/publication")->assertOk();
    $next = MunicipalPoaDocument::findOrFail($nextId);
    if ($missing) {
        Storage::disk('local')->delete($next->pdf_path);
    } else {
        Storage::disk('local')->put($next->pdf_path, 'No es PDF.');
    }
    $this->putJson($this->url."/{$nextId}/publication")->assertUnprocessable();
    expect(MunicipalPoaDocument::findOrFail($firstId)->is_current)->toBeTrue()
        ->and($next->fresh()->published_at)->toBeNull();
})->with([true, false]);

it('limpia la carga nueva si falla el registro después de insertar el documento', function (): void {
    $event = 'eloquent.created: '.MunicipalPoaDocument::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo de registro simulado.');
    });
    try {
        $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Fallo', 'pdf' => ($this->pdf)()])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }
    expect($this->poa->documents()->exists())->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('no crea registros si falla el almacenamiento del PDF', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Fallo', 'pdf' => ($this->pdf)()])->assertInternalServerError();
    expect($this->poa->documents()->exists())->toBeFalse();
});

it('encola limpieza si no puede borrar una carga fallida', function (): void {
    Queue::fake([CleanupMunicipalUpload::class]);
    $realDisk = Storage::disk('local');
    $proxy = Mockery::mock($realDisk)->makePartial();
    $proxy->shouldReceive('delete')->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
    $event = 'eloquent.created: '.MunicipalPoaDocument::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo de registro.');
    });
    try {
        $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Fallo', 'pdf' => ($this->pdf)()])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }
    Queue::assertPushed(CleanupMunicipalUpload::class);
    expect($this->poa->documents()->exists())->toBeFalse()
        ->and($realDisk->allFiles())->toHaveCount(1);
});

it('la limpieza conserva archivos referenciados y rechaza rutas arbitrarias', function (): void {
    $id = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Inicial', 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    $document = MunicipalPoaDocument::findOrFail($id);
    (new CleanupMunicipalUpload($document->pdf_path))->handle();
    Storage::disk('local')->assertExists($document->pdf_path);
    $orphan = 'municipal/poas/'.$this->poa->id.'/'.Str::uuid().'.pdf';
    Storage::disk('local')->put($orphan, '%PDF-1.4');
    (new CleanupMunicipalUpload($orphan))->handle();
    Storage::disk('local')->assertMissing($orphan);
    Storage::disk('local')->assertExists($document->pdf_path);
    expect(fn () => (new CleanupMunicipalUpload('../secret.pdf'))->handle())->toThrow(InvalidArgumentException::class);
});

it('revierte la publicación si falla después de retirar el estado actual anterior', function (): void {
    $this->user->givePermissionTo('municipal.documents.publish');
    $firstId = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Inicial', 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    $this->version = app(MunicipalPoaVersionService::class)->create($this->poa);
    $nextId = $this->postJson($this->url, ['municipal_poa_version_id' => $this->version->id, 'title' => 'Nuevo', 'pdf' => ($this->pdf)()])->assertCreated()->json('data.id');
    $this->putJson($this->url."/{$firstId}/publication")->assertOk();
    $event = 'eloquent.updating: '.MunicipalPoaVersion::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo de publicación.');
    });
    try {
        $this->putJson($this->url."/{$nextId}/publication")->assertInternalServerError();
    } finally {
        Event::forget($event);
    }
    expect(MunicipalPoaDocument::findOrFail($firstId)->is_current)->toBeTrue()
        ->and(MunicipalPoaDocument::findOrFail($nextId)->published_at)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
});
