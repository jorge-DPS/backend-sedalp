<?php

use App\Jobs\Media\CleanupImageFiles;
use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use App\Models\User;
use App\Services\Media\ImageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

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
    $news = new News;

    $news->fill([
        'title' => 'Noticia multimedia',
        'subtitle' => null,
        'excerpt' => 'Resumen multimedia.',
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => 'draft',
        'published_at' => null,
    ]);

    $news->slug = '200000000009';
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
            'media_cleanup_pending' => false,
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
it('encola la limpieza cuando falla la eliminación física de una imagen', function () {
    Queue::fake();

    $image = createImageForMediaTest(
        $this->news,
        'cccccccc-cccc-cccc-cccc-cccccccccccc',
        0
    );

    $imageService = Mockery::mock(
        ImageService::class
    );

    $imageService
        ->shouldReceive('delete')
        ->once()
        ->with(
            'cccccccc-cccc-cccc-cccc-cccccccccccc',
            NewsImage::MEDIA_DIRECTORY,
        )
        ->andThrow(
            new RuntimeException(
                'Storage no disponible.'
            )
        );

    $this->app->instance(
        ImageService::class,
        $imageService
    );

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/news/{$this->news->id}/images/{$image->id}"
        )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
            'media_cleanup_pending' => true,
        ]);

    $this->assertDatabaseMissing(
        'news_images',
        [
            'id' => $image->id,
        ]
    );

    Queue::assertPushed(
        CleanupImageFiles::class,
        function (
            CleanupImageFiles $job
        ): bool {
            return $job->filename
                === 'cccccccc-cccc-cccc-cccc-cccccccccccc'
                && $job->directory
                === NewsImage::MEDIA_DIRECTORY;
        }
    );
});

it('encola la limpieza cuando falla después de almacenar y la transacción revierte', function () {
    Queue::fake();

    $filename = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    createImageForMediaTest(
        $this->news,
        $filename,
        0
    );

    $imageService = Mockery::mock(
        ImageService::class
    );

    $imageService
        ->shouldReceive('store')
        ->once()
        ->andReturn($filename);

    $imageService
        ->shouldReceive('delete')
        ->once()
        ->with(
            $filename,
            NewsImage::MEDIA_DIRECTORY,
        )
        ->andThrow(
            new RuntimeException(
                'Storage no disponible.'
            )
        );

    $this->app->instance(
        ImageService::class,
        $imageService
    );

    $image = UploadedFile::fake()->image(
        'duplicada.jpg',
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
                        'alt' => 'Imagen duplicada',
                    ],
                ],
            ],
            [
                'Accept' => 'application/json',
            ]
        )
        ->assertServerError();

    Queue::assertPushed(
        CleanupImageFiles::class,
        fn (CleanupImageFiles $job): bool => $job->filename === $filename
            && $job->directory === NewsImage::MEDIA_DIRECTORY
    );
});

it('elimina una selección, borra sus variantes y permite reordenar las restantes', function () {
    $images = collect(range(0, 4))->map(fn (int $position) => createImageForMediaTest(
        $this->news, (string) Str::uuid(), $position,
    ));
    $disk = Storage::disk(config('media.disk', 'public'));
    foreach ($images as $image) {
        foreach (['webp', 'png', 'jpeg'] as $extension) {
            $disk->put(NewsImage::MEDIA_DIRECTORY."/{$image->filename}.{$extension}", 'test');
        }
    }

    $remaining = [$images[1]->id, $images[3]->id];
    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/admin/news/{$this->news->id}/images", [
            'ids' => [$images[0]->id, $images[2]->id, $images[4]->id],
        ])
        ->assertOk()
        ->assertJsonPath('media_cleanup_pending', false)
        ->assertJsonPath('data.0.id', $remaining[0])
        ->assertJsonPath('data.0.position', 0)
        ->assertJsonPath('data.1.id', $remaining[1])
        ->assertJsonPath('data.1.position', 1)
        ->assertJsonCount(2, 'data');

    foreach ($images as $index => $image) {
        foreach (['webp', 'png', 'jpeg'] as $extension) {
            $path = NewsImage::MEDIA_DIRECTORY."/{$image->filename}.{$extension}";
            if (in_array($index, [0, 2, 4])) {
                $disk->assertMissing($path);
            } else {
                $disk->assertExists($path);
            }
        }
    }
    $this->putJson("/api/admin/news/{$this->news->id}/images/reorder", [
        'items' => [
            ['id' => $remaining[1], 'position' => 0],
            ['id' => $remaining[0], 'position' => 1],
        ],
    ])->assertOk()->assertJsonPath('data.0.id', $remaining[1]);
});

it('rechaza selecciones vacías, duplicadas o demasiado grandes', function (array $ids) {
    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => $ids])
        ->assertUnprocessable();
})->with([
    'vacía' => [[]],
    'duplicada' => [[1, 1]],
    'límite' => [range(1, 101)],
    'identificador inválido' => [['invalid']],
]);

it('protege la eliminación múltiple con autenticación y permisos', function () {
    $url = "/api/admin/news/{$this->news->id}/images";
    $this->deleteJson($url, ['ids' => [1]])->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')
        ->deleteJson($url, ['ids' => [1]])->assertForbidden();
});

it('rechaza toda la selección si incluye una imagen ajena o inexistente', function () {
    $own = createImageForMediaTest($this->news, (string) Str::uuid(), 0);
    $otherNews = $this->news->replicate();
    $otherNews->slug = '200000000010';
    $otherNews->save();
    $other = createImageForMediaTest($otherNews, (string) Str::uuid(), 0);

    foreach ([$other->id, 99999999] as $invalidId) {
        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => [$own->id, $invalidId]])
            ->assertUnprocessable();
        $this->assertDatabaseHas('news_images', ['id' => $own->id, 'position' => 0]);
        $this->assertDatabaseHas('news_images', ['id' => $other->id]);
    }
});

it('permite eliminar todas las imágenes', function () {
    $image = createImageForMediaTest($this->news, (string) Str::uuid(), 0);
    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => [$image->id]])
        ->assertOk()->assertJsonCount(0, 'data');
    expect($this->news->images()->count())->toBe(0);
});

it('continúa limpiando las demás imágenes cuando falla un archivo', function () {
    Queue::fake();
    $first = createImageForMediaTest($this->news, (string) Str::uuid(), 0);
    $second = createImageForMediaTest($this->news, (string) Str::uuid(), 1);
    $service = Mockery::mock(ImageService::class);
    $service->shouldReceive('delete')->once()->with($first->filename, NewsImage::MEDIA_DIRECTORY)
        ->andThrow(new RuntimeException('Storage no disponible.'));
    $service->shouldReceive('delete')->once()->with($second->filename, NewsImage::MEDIA_DIRECTORY);
    $this->app->instance(ImageService::class, $service);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => [$first->id, $second->id]])
        ->assertOk()->assertJsonPath('media_cleanup_pending', true)
        ->assertJsonPath('media_cleanup_retry_failed', false)->assertJsonCount(0, 'data');
    Queue::assertPushed(CleanupImageFiles::class, fn (CleanupImageFiles $job) => $job->filename === $first->filename);
});

it('informa cuando tampoco se puede programar la limpieza física', function () {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Cola no disponible.'));
    $image = createImageForMediaTest($this->news, (string) Str::uuid(), 0);
    $service = Mockery::mock(ImageService::class);
    $service->shouldReceive('delete')->once()->andThrow(new RuntimeException('Storage no disponible.'));
    $this->app->instance(ImageService::class, $service);

    $this->actingAs($this->user, 'api')
        ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => [$image->id]])
        ->assertOk()->assertJsonPath('media_cleanup_pending', true)
        ->assertJsonPath('media_cleanup_retry_failed', true)->assertJsonCount(0, 'data');
    $this->assertDatabaseMissing('news_images', ['id' => $image->id]);
});

it('revierte la eliminación si falla la actualización del orden', function () {
    $first = createImageForMediaTest($this->news, (string) Str::uuid(), 0);
    $second = createImageForMediaTest($this->news, (string) Str::uuid(), 1);
    $service = Mockery::mock(ImageService::class);
    $service->shouldNotReceive('delete');
    $this->app->instance(ImageService::class, $service);
    $dispatcher = NewsImage::getEventDispatcher();
    NewsImage::setEventDispatcher(clone $dispatcher);
    NewsImage::updating(function () {
        throw new RuntimeException('Fallo de orden.');
    });

    try {
        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/admin/news/{$this->news->id}/images", ['ids' => [$first->id]])
            ->assertServerError();
        $this->assertDatabaseHas('news_images', ['id' => $first->id, 'position' => 0]);
        $this->assertDatabaseHas('news_images', ['id' => $second->id, 'position' => 1]);
    } finally {
        NewsImage::setEventDispatcher($dispatcher);
    }
});
