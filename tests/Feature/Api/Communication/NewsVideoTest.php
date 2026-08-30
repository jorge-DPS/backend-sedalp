<?php

use App\Models\Communication\News;
use App\Models\Communication\NewsVideo;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->videoRole = Role::create([
        'name' => 'news_videos_test',
        'guard_name' => 'api',
    ]);

    $this->videoRole->givePermissionTo([
        'news.view',
        'news.update',
    ]);

    $this->user = User::factory()->create([
        'email' => 'videos@test.com',
    ]);

    $this->user->assignRole($this->videoRole);

    $this->news = createNewsForVideoTest(
        $this->user
    );
});

function createNewsForVideoTest(User $creator): News
{
    $news = new News();

    $news->fill([
        'title' => 'Noticia con videos',
        'subtitle' => null,
        'excerpt' => 'Resumen para pruebas.',
        'description' => 'Descripción para pruebas.',
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => 'draft',
        'published_at' => null,
    ]);

    $news->slug = 'noticia-con-videos';
    $news->created_by = $creator->id;

    $news->save();

    return $news;
}

function createVideoForVideoTest(
    News $news,
    string $url,
    string $title,
    int $position
): NewsVideo {
    return $news->videos()->create([
        'youtube_url' => $url,
        'title' => $title,
        'position' => $position,
    ]);
}

it('rechaza crear videos sin autenticación', function () {
    $this
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=test123',
                'title' => 'Video de prueba',
            ]
        )
        ->assertUnauthorized();
});

it('rechaza crear videos sin news.update', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=test123',
                'title' => 'Video de prueba',
            ]
        )
        ->assertForbidden();
});

it('crea un video correctamente', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=test123',
                'title' => 'Video institucional',
            ]
        );

    $response->assertSuccessful();

    $this->assertDatabaseHas('news_videos', [
        'news_id' => $this->news->id,
        'youtube_url' => 'https://www.youtube.com/watch?v=test123',
        'title' => 'Video institucional',
        'position' => 0,
    ]);

    $this->assertDatabaseHas('news', [
        'id' => $this->news->id,
        'updated_by' => $this->user->id,
    ]);
});

it('asigna posiciones consecutivas a nuevos videos', function () {
    createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=primero',
        'Primer video',
        0
    );

    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=segundo',
                'title' => 'Segundo video',
            ]
        )
        ->assertSuccessful();

    $this->assertDatabaseHas('news_videos', [
        'news_id' => $this->news->id,
        'title' => 'Segundo video',
        'position' => 1,
    ]);
});

it('rechaza una URL inválida', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'esto-no-es-una-url',
                'title' => 'Video inválido',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'youtube_url'
        );
});

it('rechaza crear video sin título', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            "/api/admin/news/{$this->news->id}/videos",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=test123',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'title'
        );
});

it('actualiza un video', function () {
    $video = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=original',
        'Título original',
        0
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$this->news->id}/videos/{$video->id}",
            [
                'youtube_url' => 'https://www.youtube.com/watch?v=actualizado',
                'title' => 'Título actualizado',
            ]
        )
        ->assertSuccessful();

    $this->assertDatabaseHas('news_videos', [
        'id' => $video->id,
        'youtube_url' => 'https://www.youtube.com/watch?v=actualizado',
        'title' => 'Título actualizado',
    ]);

    $this->assertDatabaseHas('news', [
        'id' => $this->news->id,
        'updated_by' => $this->user->id,
    ]);
});

it('reordena todos los videos', function () {
    $first = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video1',
        'Primer video',
        0
    );

    $second = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video2',
        'Segundo video',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/videos/reorder",
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
        ->assertSuccessful();

    $this->assertDatabaseHas('news_videos', [
        'id' => $first->id,
        'position' => 1,
    ]);

    $this->assertDatabaseHas('news_videos', [
        'id' => $second->id,
        'position' => 0,
    ]);
});

it('rechaza reordenar si faltan videos', function () {
    $first = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video1',
        'Primer video',
        0
    );

    createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video2',
        'Segundo video',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/videos/reorder",
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
    $first = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video1',
        'Primer video',
        0
    );

    $second = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video2',
        'Segundo video',
        1
    );

    $this
        ->actingAs($this->user, 'api')
        ->putJson(
            "/api/admin/news/{$this->news->id}/videos/reorder",
            [
                'items' => [
                    [
                        'id' => $first->id,
                        'position' => 0,
                    ],
                    [
                        'id' => $second->id,
                        'position' => 3,
                    ],
                ],
            ]
        )
        ->assertUnprocessable();
});

it('elimina un video y normaliza las posiciones', function () {
    $first = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video1',
        'Primer video',
        0
    );

    $second = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video2',
        'Segundo video',
        1
    );

    $third = createVideoForVideoTest(
        $this->news,
        'https://www.youtube.com/watch?v=video3',
        'Tercer video',
        2
    );

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/news/{$this->news->id}/videos/{$second->id}"
        )
        ->assertSuccessful();

    $this->assertDatabaseMissing('news_videos', [
        'id' => $second->id,
    ]);

    $this->assertDatabaseHas('news_videos', [
        'id' => $first->id,
        'position' => 0,
    ]);

    $this->assertDatabaseHas('news_videos', [
        'id' => $third->id,
        'position' => 1,
    ]);
});
