<?php

use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use App\Models\User;
use Database\Seeders\NewsPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(NewsPermissionsSeeder::class);

    $this->role = Role::create([
        'name' => 'news_images_test',
        'guard_name' => 'api',
    ]);

    $this->role->givePermissionTo([
        'news.view',
        'news.update',
    ]);

    $this->user = User::factory()->create([
        'email' => 'imagenes@test.com',
    ]);

    $this->user->assignRole($this->role);

    /*
     * Evitamos escribir archivos reales durante las pruebas.
     */
    Storage::fake(
        config('media.disk', 'public')
    );

    $this->news = createNewsForMediaTest(
        $this->user
    );
});

function createNewsForMediaTest(User $creator): News
{
    $news = new News();

    $news->fill([
        'title' => 'Noticia multimedia',
        'subtitle' => null,
        'excerpt' => 'Resumen multimedia.',
        'description' => 'Descripción multimedia.',
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => 'draft',
        'published_at' => null,
    ]);

    $news->slug = 'noticia-multimedia';
    $news->created_by = $creator->id;

    $news->save();

    return $news;
}

function createImageForMediaTest(
    News $news,
    string $filename,
    int $position
): NewsImage {
    return $news->images()->create([
        'filename' => $filename,
        'alt' => "Imagen {$position}",
        'caption' => null,
        'position' => $position,
    ]);
}

it('rechaza subir imágenes sin autenticación', function () {
    $this->postJson(
        "/api/admin/news/{$this->news->id}/images"
    )->assertUnauthorized();
});

it('rechaza subir imágenes sin news.update', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/images"
        )
        ->assertForbidden();
});

it('rechaza una petición sin imágenes', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/images",
            []
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images');
});

it('sube una imagen correctamente', function () {
    $image = UploadedFile::fake()->image(
        'noticia.jpg',
        1200,
        800
    );

    $response = $this
        ->actingAs($this->user, 'api')
        ->post(
            "/api/admin/news/{$this->news->id}/images",
            [
                'images' => [
                    [
                        'file' => $image,
                        'alt' => 'Obra en ejecución',
                        'caption' => 'Fotografía de prueba',
                    ],
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        );

    $response->assertOk();

    $this->assertDatabaseHas('news_images', [
        'news_id' => $this->news->id,
        'alt' => 'Obra en ejecución',
        'caption' => 'Fotografía de prueba',
        'position' => 0,
    ]);

    $this->assertDatabaseHas('news', [
        'id' => $this->news->id,
        'updated_by' => $this->user->id,
    ]);
});

it('asigna posiciones consecutivas a nuevas imágenes', function () {
    createImageForMediaTest(
        $this->news,
        '11111111-1111-1111-1111-111111111111',
        0
    );

    $image = UploadedFile::fake()->image(
        'segunda.png',
        800,
        600
    );

    $this
        ->actingAs($this->user, 'api')
        ->post(
            "/api/admin/news/{$this->news->id}/images",
            [
                'images' => [
                    [
                        'file' => $image,
                        'alt' => 'Segunda imagen',
                    ],
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news_images', [
        'news_id' => $this->news->id,
        'alt' => 'Segunda imagen',
        'position' => 1,
    ]);
});

it('actualiza alt y caption de una imagen', function () {
    $image = createImageForMediaTest(
        $this->news,
        '22222222-2222-2222-2222-222222222222',
        0
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$this->news->id}/images/{$image->id}",
            [
                'alt' => 'Texto alternativo actualizado',
                'caption' => 'Descripción actualizada',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news_images', [
        'id' => $image->id,
        'alt' => 'Texto alternativo actualizado',
        'caption' => 'Descripción actualizada',
    ]);

    $this->assertDatabaseHas('news', [
        'id' => $this->news->id,
        'updated_by' => $this->user->id,
    ]);
});

it('reordena todas las imágenes de una noticia', function () {
    $first = createImageForMediaTest(
        $this->news,
        '33333333-3333-3333-3333-333333333333',
        0
    );

    $second = createImageForMediaTest(
        $this->news,
        '44444444-4444-4444-4444-444444444444',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/images/reorder",
            [
                'items' => [
                    [
                        'id' => $first->id,
                        'position' => 1,
                    ],
                    [
                        'id' => $second->id,
                        'position' => 0,
                    ],
                ],
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news_images', [
        'id' => $first->id,
        'position' => 1,
    ]);

    $this->assertDatabaseHas('news_images', [
        'id' => $second->id,
        'position' => 0,
    ]);
});

it('rechaza reordenar si faltan imágenes', function () {
    $first = createImageForMediaTest(
        $this->news,
        '55555555-5555-5555-5555-555555555555',
        0
    );

    createImageForMediaTest(
        $this->news,
        '66666666-6666-6666-6666-666666666666',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/images/reorder",
            [
                'items' => [
                    [
                        'id' => $first->id,
                        'position' => 0,
                    ],
                ],
            ]
        )
        ->assertUnprocessable();
});

it('rechaza posiciones no consecutivas', function () {
    $first = createImageForMediaTest(
        $this->news,
        '77777777-7777-7777-7777-777777777777',
        0
    );

    $second = createImageForMediaTest(
        $this->news,
        '88888888-8888-8888-8888-888888888888',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/images/reorder",
            [
                'items' => [
                    [
                        'id' => $first->id,
                        'position' => 0,
                    ],
                    [
                        'id' => $second->id,
                        'position' => 2,
                    ],
                ],
            ]
        )
        ->assertUnprocessable();
});

it('elimina una imagen y normaliza las posiciones', function () {
    $first = createImageForMediaTest(
        $this->news,
        '99999999-9999-9999-9999-999999999999',
        0
    );

    $second = createImageForMediaTest(
        $this->news,
        'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        1
    );

    $third = createImageForMediaTest(
        $this->news,
        'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        2
    );

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/news/{$this->news->id}/images/{$second->id}"
        )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
        ]);

    $this->assertDatabaseMissing('news_images', [
        'id' => $second->id,
    ]);

    $this->assertDatabaseHas('news_images', [
        'id' => $first->id,
        'position' => 0,
    ]);

    $this->assertDatabaseHas('news_images', [
        'id' => $third->id,
        'position' => 1,
    ]);
});
