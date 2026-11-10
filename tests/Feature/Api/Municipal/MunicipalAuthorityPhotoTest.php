<?php

use App\Jobs\Media\CleanupImageFiles;
use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Territory\Municipality;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    $user = User::factory()->create();
    $user->assignRole('tecnico');
    $this->actingAs($user, 'api');
    $this->municipality = Municipality::factory()->create();
    $this->authority = MunicipalAuthority::factory()->for($this->municipality)->create();
    $this->url = "/api/admin/municipalities/{$this->municipality->id}/authorities/{$this->authority->id}/photo";
});

it('procesa fotografías privadas con rutas seguras y conserva la fotografía anterior al reemplazarla', function (): void {
    $originalDisk = config('media.disk');
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('portrait.jpg', 900, 1000)])
        ->assertOk()->assertJsonPath('data.has_photo', true)->assertJsonMissingPath('data.photo_path')->assertJsonMissingPath('data.photo_url');
    $first = $this->authority->fresh()->photo_path;
    $firstBytes = Storage::disk('local')->get($first);

    expect($first)->toStartWith("municipal/authorities/{$this->authority->id}/")->toEndWith('.webp')
        ->and(Storage::disk('local')->mimeType($first))->toBe('image/webp')
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($this->authority->fresh()->toArray())->not->toHaveKey('photo_path');
    $this->get($this->url)->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent($firstBytes);
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('portrait.png', 700, 900)])->assertOk();

    expect($this->authority->fresh()->photo_path)->not->toBe($first)
        ->and(Storage::disk('local')->get($first))->toBe($firstBytes)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2)
        ->and(config('media.disk'))->toBe($originalDisk);
    $this->deleteJson($this->url)->assertMethodNotAllowed();
});

it('respeta el disco público configurado para otros módulos sin usarlo para fotografías municipales', function (): void {
    config(['media.disk' => 'public']);
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('portrait.jpg')])->assertOk();
    expect(Storage::disk('local')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(config('media.disk'))->toBe('public');
});

it('rechaza formatos no permitidos y contenido que no es una imagen', function (string $name): void {
    $file = UploadedFile::fake()->createWithContent($name, '<script>alert(1)</script>');
    $this->postJson($this->url, ['photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
    expect($this->authority->fresh()->photo_path)->toBeNull()->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['photo.svg', 'photo.gif', 'fake.jpg']);

it('aplica límites configurables de tamaño y dimensiones', function (): void {
    config(['municipal.photo_max_upload_kb' => 1]);
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('large.jpg')->size(2)])->assertUnprocessable()->assertJsonValidationErrors('photo');
    config(['municipal.photo_max_upload_kb' => 5120]);
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('wide.jpg', 8001, 1)])->assertUnprocessable()->assertJsonValidationErrors('photo');
});

it('rechaza rutas proporcionadas por el cliente y no accede a rutas ajenas al directorio de la autoridad', function (): void {
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('portrait.png'), 'photo_path' => '../../secret.webp'])
        ->assertUnprocessable()->assertJsonValidationErrors('photo_path');
    $this->authority->update(['photo_path' => 'communication/news/private.webp']);
    Storage::disk('local')->put('communication/news/private.webp', 'secret');
    $this->getJson($this->url)->assertNotFound();
    $this->authority->update(['photo_path' => null]);
    $this->getJson($this->url)->assertNotFound();
});

it('revierte metadatos y limpia solo la fotografía nueva si falla guardar la autoridad', function (): void {
    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('first.jpg')])->assertOk();
    $first = $this->authority->fresh()->photo_path;
    $event = 'eloquent.updated: '.MunicipalAuthority::class;
    Event::listen($event, function (): void {
        throw new RuntimeException('Fallo al registrar fotografía.');
    });
    try {
        $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('next.png')])->assertInternalServerError();
    } finally {
        Event::forget($event);
    }
    expect($this->authority->fresh()->photo_path)->toBe($first)
        ->and(Storage::disk('local')->allFiles())->toBe([$first]);
    (new CleanupMunicipalUpload($first))->handle();
    Storage::disk('local')->assertExists($first);
});

it('no registra fotografías si falla almacenar la imagen y reintenta la limpieza en el disco privado', function (): void {
    Queue::fake([CleanupImageFiles::class]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->with(Mockery::type('string'), Mockery::type('string'), 'private')->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    $this->postJson($this->url, ['photo' => UploadedFile::fake()->image('portrait.jpg')])->assertInternalServerError();
    expect($this->authority->fresh()->photo_path)->toBeNull();
    Queue::assertPushed(CleanupImageFiles::class, fn (CleanupImageFiles $job): bool => $job->disk === 'local'
        && $job->directory === 'municipal/authorities/'.$this->authority->id);
});
