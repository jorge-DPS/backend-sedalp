<?php

use App\Jobs\Municipal\CompleteMunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\User;
use App\Services\Municipal\MunicipalPoaUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->realQueue = app('queue');
    Queue::fake([CompleteMunicipalPoaUpload::class]);
    config(['municipal.poa_chunk_bytes' => 12]);
    $this->user = User::factory()->create();
    $this->user->assignRole('tecnico');
    $this->actingAs($this->user, 'api');
    $this->version = MunicipalPoaVersion::factory()->create();
    $this->versionUrl = '/api/admin/municipalities/'.$this->version->municipalPoa->municipality_id.'/poas/'.$this->version->municipal_poa_id.'/versions/'.$this->version->id;
    $this->url = $this->versionUrl.'/uploads';
    $this->bytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
    $this->payload = [
        'idempotency_key' => (string) Str::uuid(), 'title' => 'POA con anexos',
        'original_filename' => 'POA institucional.pdf', 'expected_size' => strlen($this->bytes), 'sha256' => hash('sha256', $this->bytes),
    ];
    $this->sendChunk = function (string $id, int $index, ?string $contents = null, ?string $sha = null) {
        $contents ??= substr($this->bytes, $index * 12, 12);

        return $this->putJson($this->url.'/'.$id.'/chunks/'.$index, [
            'chunk' => UploadedFile::fake()->createWithContent('fragment.bin', $contents), 'sha256' => $sha ?? hash('sha256', $contents),
        ]);
    };
    $this->sendAll = function (string $id): void {
        for ($index = 0; $index < (int) ceil(strlen($this->bytes) / 12); $index++) {
            ($this->sendChunk)($id, $index)->assertOk();
        }
    };
});

it('reanuda fragmentos fuera de orden y finaliza idempotentemente un único PDF privado', function (): void {
    $id = $this->postJson($this->url, $this->payload)->assertCreated()->assertJsonPath('data.chunk_size', 12)->json('data.id');
    for ($index = (int) ceil(strlen($this->bytes) / 12) - 1; $index >= 0; $index--) {
        ($this->sendChunk)($id, $index)->assertOk();
    }
    ($this->sendChunk)($id, 0)->assertOk();
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.received_chunks', [0, 1, 2])
        ->assertJsonPath('data.received_bytes', strlen($this->bytes))->assertJsonMissingPath('data.metadata');
    expect(MunicipalPoaDocument::count())->toBe(0);
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    Queue::assertPushed(CompleteMunicipalPoaUpload::class, 1);
    $job = new CompleteMunicipalPoaUpload($id);
    $job->handle(app(MunicipalPoaUploadService::class));
    $job->handle(app(MunicipalPoaUploadService::class));
    $document = MunicipalPoaDocument::sole();
    expect($document->file_size_bytes)->toBe(strlen($this->bytes))
        ->and($document->original_filename)->toBe('POA institucional.pdf')
        ->and(Storage::disk('local')->get($document->pdf_path))->toBe($this->bytes);
    $this->getJson($this->url.'/'.$id)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.document_id', $document->id);
    $this->postJson($this->url.'/'.$id.'/complete')->assertOk();
    $this->deleteJson($this->url.'/'.$id)->assertConflict();
    $this->getJson(str_replace('/admin/', '/public/', $this->versionUrl).'/documents/'.$document->id.'/file')->assertNotFound();
    expect(Storage::disk('local')->files('municipal/poa-uploads/'.$id))->toHaveCount(1);
});

it('reutiliza la clave inicial solo cuando pertenece a la misma transferencia', function (): void {
    $id = $this->postJson($this->url, $this->payload)->assertCreated()->json('data.id');
    $this->postJson($this->url, $this->payload)->assertCreated()->assertJsonPath('data.id', $id);
    $this->postJson($this->url, [...$this->payload, 'title' => 'Otro documento'])->assertConflict();
    expect(MunicipalPoaUpload::count())->toBe(1);
});

it('valida tamaño índice digest y reintentos incompatibles sin registrar documentos', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendChunk)($id, 0, null, str_repeat('0', 64))->assertUnprocessable();
    ($this->sendChunk)($id, 999, 'x')->assertUnprocessable();
    $this->putJson($this->url.'/'.$id.'/chunks/'.str_repeat('9', 60), [
        'chunk' => UploadedFile::fake()->createWithContent('fragment.bin', substr($this->bytes, 0, 12)),
        'sha256' => hash('sha256', substr($this->bytes, 0, 12)),
    ])->assertUnprocessable()->assertJsonValidationErrors('index');
    ($this->sendChunk)($id, 0, 'corto')->assertUnprocessable();
    ($this->sendChunk)($id, 0)->assertOk();
    ($this->sendChunk)($id, 0, str_repeat('x', 12))->assertConflict();
    $this->postJson($this->url, [...$this->payload, 'original_filename' => '../privado.pdf'])->assertUnprocessable();
    $this->postJson($this->url.'/'.$id.'/complete')->assertUnprocessable();
    expect(MunicipalPoaDocument::count())->toBe(0);
});

it('comprueba permiso propietario y versión en cada operación de carga', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    $other = User::factory()->create();
    $other->assignRole('tecnico');
    $this->actingAs($other, 'api')->getJson($this->url.'/'.$id)->assertNotFound();
    ($this->sendChunk)($id, 0)->assertNotFound();
    $other->removeRole('tecnico');
    $other->assignRole('director');
    $this->getJson($this->url.'/'.$id)->assertForbidden();
    $this->postJson($this->url, $this->payload)->assertForbidden();
    $this->actingAs($this->user, 'api');
    $foreign = MunicipalPoaVersion::factory()->create();
    $foreignUrl = '/api/admin/municipalities/'.$foreign->municipalPoa->municipality_id.'/poas/'.$foreign->municipal_poa_id.'/versions/'.$foreign->id.'/uploads/'.$id;
    $this->getJson($foreignUrl)->assertNotFound();
});

it('bloquea publicación con transferencias pendientes y permite cancelarlas explícitamente', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendChunk)($id, 0)->assertOk();
    $this->postJson($this->versionUrl.'/documents', ['title' => 'Completo', 'pdf' => UploadedFile::fake()->createWithContent('completo.pdf', $this->bytes)])->assertCreated();
    $this->user->givePermissionTo('municipal.documents.publish');
    $this->putJson($this->versionUrl.'/publication')->assertUnprocessable();
    $this->deleteJson($this->url.'/'.$id)->assertNoContent();
    $this->putJson($this->versionUrl.'/publication')->assertOk();
    Storage::disk('local')->assertMissing('municipal/poa-uploads/'.$id);
    expect(MunicipalPoaDocument::count())->toBe(1);
});

it('serializa escrituras concurrentes y permite reintentar después del bloqueo', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    Storage::disk('local')->makeDirectory('municipal/poa-uploads/'.$id);
    $lock = fopen(Storage::disk('local')->path('municipal/poa-uploads/'.$id.'/.lock'), 'c+b');
    flock($lock, LOCK_EX);
    try {
        ($this->sendChunk)($id, 0)->assertConflict();
        expect(MunicipalPoaUpload::findOrFail($id)->metadata['chunks'])->toBe([]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    ($this->sendChunk)($id, 0)->assertOk();
});

it('detecta SHA final incorrecto y contenido distinto de PDF sin dejar registros definitivos', function (bool $pdf): void {
    if (! $pdf) {
        $this->bytes = str_repeat('texto sin PDF ', 3);
        $this->payload['expected_size'] = strlen($this->bytes);
        $this->payload['sha256'] = hash('sha256', $this->bytes);
    } else {
        $this->payload['sha256'] = str_repeat('0', 64);
    }
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    expect(fn () => (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class)))->toThrow(ValidationException::class);
    expect(MunicipalPoaDocument::count())->toBe(0)->and(MunicipalPoaUpload::findOrFail($id)->status)->toBe('failed');
    Storage::disk('local')->assertMissing('municipal/poas/'.$this->version->municipal_poa_id.'/'.$id.'.pdf');
})->with([true, false]);

it('recupera una transferencia si falla registrar el documento después de ensamblarlo', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    $event = 'eloquent.created: '.MunicipalPoaDocument::class;
    Event::listen($event, fn () => throw new RuntimeException('Fallo de registro simulado.'));
    try {
        expect(fn () => (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class)))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($event);
    }
    expect(MunicipalPoaDocument::count())->toBe(0)->and(MunicipalPoaUpload::findOrFail($id)->document_id)->toBeNull();
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class));
    expect(MunicipalPoaDocument::count())->toBe(1);
});

it('detecta corrupción de fragmentos en disco y permite reparar con el mismo contenido original', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    Storage::disk('local')->put('municipal/poa-uploads/'.$id.'/0.part', str_repeat('x', 12));
    expect(fn () => (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class)))->toThrow(ValidationException::class);
    ($this->sendChunk)($id, 0)->assertOk();
    (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class));
    expect(Storage::disk('local')->get(MunicipalPoaDocument::sole()->pdf_path))->toBe($this->bytes);
});

it('reanuda con enteros de 64 bits un archivo declarado superior a dos GiB sin reservarlo en memoria', function (): void {
    config(['municipal.poa_chunk_bytes' => 8388608]);
    $size = 2147483648 + 4096;
    $id = $this->postJson($this->url, [...$this->payload, 'expected_size' => $size])->assertCreated()
        ->assertJsonPath('data.expected_size', $size)->assertJsonPath('data.total_chunks', 257)->json('data.id');
    $upload = MunicipalPoaUpload::findOrFail($id);
    expect($upload->expected_size)->toBe($size)->and($upload->chunkLength(256))->toBe(4096)
        ->and(MunicipalPoaDocument::count())->toBe(0);
    $this->postJson($this->url.'/'.$id.'/complete')->assertUnprocessable();
});

it('limpia temporales expirados sin tocar documentos completados y omite cargas bloqueadas', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendChunk)($id, 0)->assertOk();
    $this->travel(25)->hours();
    $this->getJson($this->url.'/'.$id)->assertGone();
    $lock = fopen(Storage::disk('local')->path('municipal/poa-uploads/'.$id.'/.lock'), 'c+b');
    flock($lock, LOCK_EX);
    try {
        $this->artisan('municipal:prune-poa-uploads')->assertSuccessful();
        expect(MunicipalPoaUpload::whereKey($id)->exists())->toBeTrue();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    $this->artisan('municipal:prune-poa-uploads')->assertSuccessful();
    expect(MunicipalPoaUpload::whereKey($id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('municipal/poa-uploads/'.$id);
    expect((new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class)))->toBeNull();
    $this->travelBack();
});

it('vuelve a comprobar autorización en el trabajador antes de registrar el archivo', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    $this->user->removeRole('tecnico');
    expect(fn () => (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class)))
        ->toThrow(HttpException::class);
    expect(MunicipalPoaDocument::count())->toBe(0)->and(MunicipalPoaUpload::findOrFail($id)->status)->toBe('failed');
});

it('conserva el documento y su sesión idempotente al limpiar otras cargas expiradas', function (): void {
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    (new CompleteMunicipalPoaUpload($id))->handle(app(MunicipalPoaUploadService::class));
    $document = MunicipalPoaDocument::sole();
    $this->travel(25)->hours();
    $this->artisan('municipal:prune-poa-uploads')->assertSuccessful();
    expect(MunicipalPoaUpload::findOrFail($id)->document_id)->toBe($document->id)
        ->and(Storage::disk('local')->get($document->pdf_path))->toBe($this->bytes);
    $this->travelBack();
});

it('procesa una transferencia mediante la cola real de base de datos y su trabajador', function (): void {
    config(['queue.connections.municipal_uploads.after_commit' => false]);
    Queue::swap($this->realQueue);
    $id = $this->postJson($this->url, $this->payload)->json('data.id');
    ($this->sendAll)($id);
    $this->postJson($this->url.'/'.$id.'/complete')->assertAccepted();
    $this->artisan('queue:work', [
        'connection' => 'municipal_uploads', '--queue' => 'municipal-uploads', '--once' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();
    expect(MunicipalPoaUpload::findOrFail($id)->status)->toBe('completed')
        ->and(Storage::disk('local')->get(MunicipalPoaDocument::sole()->pdf_path))->toBe($this->bytes);
});
