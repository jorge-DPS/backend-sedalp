<?php

use App\Models\Content\SiteMedia;
use App\Models\Content\SitePage;
use App\Models\Content\SiteSection;
use App\Models\Content\SiteSectionItem;
use App\Models\User;
use App\Services\Content\SiteMediaService;
use Database\Seeders\SiteContentPermissionsSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([SiteContentPermissionsSeeder::class, SiteContentSeeder::class]);
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['content.view', 'content.update', 'content.media.create', 'content.media.delete']);
    Storage::fake('public');
    config(['media.disk' => 'public']);
});

it('requires authentication and view permission', function (): void {
    $this->getJson('/api/admin/content/pages')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->getJson('/api/admin/content/pages')->assertForbidden();
    $this->actingAs($this->editor, 'api')->getJson('/api/admin/content/pages')->assertOk()->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.limits.description', 10000);
});

it('lists the initial pages and independent content blocks', function (): void {
    $this->actingAs($this->editor, 'api')->getJson('/api/admin/content/pages/inicio')
        ->assertOk()->assertJsonCount(6, 'data.sections')->assertJsonCount(3, 'data.sections.1.items');
    $original = SitePage::where('key', 'asistencia-tecnica')->firstOrFail()->sections()->where('key', 'hero')->firstOrFail()->title;
    $this->patchJson('/api/admin/content/pages/inicio/sections/asistencia-tecnica', ['title' => 'Nuevo resumen'])
        ->assertOk()->assertJsonPath('data.title', 'Nuevo resumen')->assertJsonPath('data.updated_by', $this->editor->id);
    expect(SitePage::where('key', 'asistencia-tecnica')->firstOrFail()->sections()->where('key', 'hero')->firstOrFail()->title)->toBe($original);
});

it('preserves edited text when seeding again', function (): void {
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', ['title' => 'Contenido editado'])->assertOk();
    $this->seed(SiteContentSeeder::class);
    expect(SitePage::count())->toBe(4)->and(SiteSection::count())->toBe(12)->and(SiteSectionItem::count())->toBe(3);
    $this->getJson('/api/admin/content/pages/inicio')->assertJsonPath('data.sections.0.title', 'Contenido editado');
});

it('accepts long unicode text without truncation', function (): void {
    $data = ['title' => str_repeat('á', 500), 'subtitle' => str_repeat('ñ', 1000), 'description' => str_repeat('ó', 10000)];
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', $data)
        ->assertOk()->assertJsonPath('data.title', $data['title'])->assertJsonPath('data.description', $data['description']);
});

it('rejects text exceeding the allowed length', function (string $field, int $max): void {
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', [$field => str_repeat('á', $max + 1)])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['title' => ['title', 500], 'subtitle' => ['subtitle', 1000], 'description' => ['description', 10000], 'eyebrow' => ['eyebrow', 200], 'image_alt' => ['image_alt', 500]]);

it('protects structure and scopes sections to their page', function (): void {
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/simred/sections/pilares', ['title' => 'Ajeno'])->assertNotFound();
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', ['key' => 'changed', 'type' => 'pillars', 'updated_by' => $this->editor->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['key', 'type', 'updated_by']);
});

it('validates options and merges partial option updates', function (): void {
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', ['options' => ['button_label' => 'Explorar']])
        ->assertOk()->assertJsonPath('data.options.button_to', '#institucional')->assertJsonPath('data.options.button_label', 'Explorar');
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', ['options' => ['html' => '<script>']])->assertUnprocessable();
    foreach (['javascript:alert(1)', '//evil.test', '/\\evil.test', 'https://evil.test'] as $link) {
        $this->patchJson('/api/admin/content/pages/inicio/sections/hero', ['options' => ['button_to' => $link]])
            ->assertUnprocessable()->assertJsonValidationErrors('options.button_to');
    }
});

it('creates updates and deletes institutional items with scoped ownership', function (): void {
    $base = '/api/admin/content/pages/inicio/sections/pilares/items';
    $response = $this->actingAs($this->editor, 'api')->postJson($base, [
        'key' => 'valores', 'title' => 'Valores', 'description' => 'Transparencia y servicio.', 'position' => 3,
    ])->assertCreated();
    $id = $response->json('data.id');
    $this->patchJson("$base/$id", ['title' => 'Nuestros valores'])->assertOk()->assertJsonPath('data.title', 'Nuestros valores');
    $this->patchJson("/api/admin/content/pages/simred/sections/hero/items/$id", ['title' => 'Ajeno'])->assertNotFound();
    $this->postJson($base, ['key' => 'valores', 'title' => 'Duplicado', 'description' => 'Texto'])->assertUnprocessable();
    $this->deleteJson("$base/$id")->assertNoContent();
    $this->assertDatabaseMissing('site_section_items', ['id' => $id]);
});

it('does not create pillar items in hero blocks', function (): void {
    $this->actingAs($this->editor, 'api')->postJson('/api/admin/content/pages/inicio/sections/hero/items', [
        'key' => 'item', 'title' => 'Texto', 'description' => 'Descripción',
    ])->assertUnprocessable();
});

it('processes both image profiles to webp and protects files in use', function (string $profile, int $width, int $height): void {
    $response = $this->actingAs($this->editor, 'api')->postJson('/api/admin/content/media', [
        'profile' => $profile, 'image' => UploadedFile::fake()->image('banner.jpg', $width + 100, $height + 100),
    ])->assertCreated()->assertJsonPath('data.mime_type', 'image/webp');
    $media = SiteMedia::findOrFail($response->json('data.id'));
    Storage::disk('public')->assertExists($media->path);
    $dimensions = getimagesize(Storage::disk('public')->path($media->path));
    expect($dimensions[0])->toBe($width)->and($dimensions[1])->toBe($height);
    $field = $profile.'_media_id';
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', [$field => $media->id])->assertOk();
    $this->deleteJson('/api/admin/content/media/'.$media->id)->assertConflict();
    Storage::disk('public')->assertExists($media->path);
    $this->getJson('/api/admin/content/media?profile='.$profile)->assertOk()->assertJsonPath('data.0.in_use', true);
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', [$field => null])->assertOk();
    $this->deleteJson('/api/admin/content/media/'.$media->id)->assertNoContent();
    Storage::disk('public')->assertMissing($media->path);
})->with(['desktop' => ['desktop', 1920, 800], 'mobile' => ['mobile', 768, 1024]]);

it('rejects invalid files and undersized images', function (): void {
    $this->actingAs($this->editor, 'api')->postJson('/api/admin/content/media', [
        'profile' => 'desktop', 'image' => UploadedFile::fake()->create('bad.svg', 2, 'image/svg+xml'),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');
    $this->postJson('/api/admin/content/media', [
        'profile' => 'mobile', 'image' => UploadedFile::fake()->image('small.jpg', 100, 100),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');
    expect(SiteMedia::count())->toBe(0);
});

it('rejects assigning the wrong profile or a nonexistent image', function (): void {
    $mobile = SiteMedia::factory()->create(['profile' => 'mobile']);
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', ['desktop_media_id' => $mobile->id])
        ->assertUnprocessable()->assertJsonValidationErrors('desktop_media_id');
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', ['mobile_media_id' => 999999])->assertUnprocessable();
    $this->patchJson('/api/admin/content/pages/inicio/sections/pilares', ['mobile_media_id' => $mobile->id])->assertUnprocessable();
});

it('enforces mutation and upload permissions independently', function (): void {
    $reader = User::factory()->create();
    $reader->givePermissionTo('content.view');
    $this->actingAs($reader, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', ['title' => 'No'])->assertForbidden();
    $this->postJson('/api/admin/content/media', [])->assertForbidden();
    $media = SiteMedia::factory()->create();
    $this->deleteJson('/api/admin/content/media/'.$media->id)->assertForbidden();
});

it('bounds and filters the image library', function (): void {
    SiteMedia::factory()->count(3)->create();
    SiteMedia::factory()->create(['profile' => 'mobile']);
    $this->actingAs($this->editor, 'api')->getJson('/api/admin/content/media?profile=mobile&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/admin/content/media?per_page=500')->assertUnprocessable();
});

it('removes processed files if database persistence fails', function (): void {
    SiteMedia::creating(function (): void {
        throw new RuntimeException('Simulated database failure');
    });
    try {
        expect(fn () => app(SiteMediaService::class)->store(
            UploadedFile::fake()->image('banner.jpg', 1920, 800), 'desktop', $this->editor->id,
        ))->toThrow(RuntimeException::class, 'Simulated database failure');
        expect(Storage::disk('public')->allFiles('content'))->toBe([]);
        expect(SiteMedia::count())->toBe(0);
    } finally {
        SiteMedia::flushEventListeners();
    }
});

it('preserves the media record when physical deletion fails', function (): void {
    $media = SiteMedia::factory()->create();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('delete')->once()->with($media->path)->andReturn(false);
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
    expect(fn () => app(SiteMediaService::class)->delete($media))
        ->toThrow(RuntimeException::class, 'No se pudo eliminar el archivo de imagen.');
    $this->assertModelExists($media);
});

it('preserves the old image when replacing a hero image', function (): void {
    $old = SiteMedia::factory()->create();
    $new = SiteMedia::factory()->create();
    Storage::disk('public')->put($old->path, 'old image');
    $this->actingAs($this->editor, 'api')->patchJson('/api/admin/content/pages/inicio/sections/hero', ['desktop_media_id' => $old->id])->assertOk();
    $this->patchJson('/api/admin/content/pages/inicio/sections/hero', ['desktop_media_id' => $new->id])->assertOk();
    Storage::disk('public')->assertExists($old->path);
    $this->assertModelExists($old);
    $this->deleteJson('/api/admin/content/media/'.$old->id)->assertNoContent();
});
