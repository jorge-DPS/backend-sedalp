<?php

use App\Models\Communication\News;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    /*
     * Rol de prueba SIN news.publish.
     */
    $this->newsRole = Role::create([
        'name' => 'news_editor_test',
        'guard_name' => 'api',
    ]);

    $this->newsRole->givePermissionTo([
        'news.view',
        'news.create',
        'news.update',
        'news.delete',
    ]);

    $this->user = User::factory()->create([
        'email' => 'editor.noticias@test.com',
    ]);

    $this->user->assignRole($this->newsRole);

    $this->validNewsData = [
        'title' => 'Nueva obra para La Paz',
        'subtitle' => 'Subtítulo de prueba',
        'excerpt' => 'Resumen de la noticia de prueba.',
        'description' => 'Descripción de la noticia de prueba.',
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => 'draft',
        'published_at' => null,
    ];
});

function createNewsForNewsTest(
    User $creator,
    string $title = 'Noticia existente',
    string $slug = 'noticia-existente',
    string $status = 'draft'
): News {
    $news = new News;

    $news->fill([
        'title' => $title,
        'subtitle' => null,
        'excerpt' => 'Resumen de prueba.',
        'description' => 'Descripción de prueba.',
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => $status,
        'published_at' => $status === 'published'
          ? now()->toDateString()
          : null,
    ]);

    $news->slug = $slug;
    $news->created_by = $creator->id;

    $news->save();

    return $news;
}

it('rechaza listar noticias sin autenticación', function () {
    $this->getJson('/api/admin/news')
        ->assertUnauthorized();
});

it('rechaza listar noticias sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/news')
        ->assertForbidden();
});

it('permite listar noticias con news.view', function () {
    createNewsForNewsTest(
        $this->user,
        'Noticia para listado',
        'noticia-para-listado'
    );

    $response = $this
        ->actingAs($this->user, 'api')
        ->getJson('/api/admin/news');

    $response
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
});

it('crea una noticia como borrador', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/news',
            $this->validNewsData
        );

    $response->assertCreated();

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => 'nueva-obra-para-la-paz',
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
});

it('genera automáticamente el slug de la noticia', function () {
    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/news',
            $this->validNewsData
        )
        ->assertCreated();

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => 'nueva-obra-para-la-paz',
    ]);
});

it('genera un slug diferente cuando ya existe', function () {
    createNewsForNewsTest(
        $this->user,
        'Nueva obra para La Paz',
        'nueva-obra-para-la-paz'
    );

    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/news',
            $this->validNewsData
        )
        ->assertCreated();

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => 'nueva-obra-para-la-paz-2',
    ]);
});

it('rechaza crear una noticia sin título', function () {
    $data = $this->validNewsData;

    unset($data['title']);

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

it('rechaza contenido con formato inválido', function () {
    $data = $this->validNewsData;

    $data['content'] = [
        'type' => 'formato-invalido',
        'content' => [],
    ];

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content.type');
});

it('impide publicar a un usuario sin news.publish', function () {
    $data = $this->validNewsData;

    $data['status'] = 'published';
    $data['published_at'] = now()->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertForbidden();

    $this->assertDatabaseMissing('news', [
        'title' => 'Nueva obra para La Paz',
        'status' => 'published',
    ]);
});

it('permite publicar a un usuario con news.publish', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $data = $this->validNewsData;

    $data['status'] = 'published';
    $data['published_at'] = now()->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertCreated();

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'status' => 'published',
    ]);
});

it('requiere fecha de publicación cuando el estado es published', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $data = $this->validNewsData;

    $data['status'] = 'published';
    $data['published_at'] = null;

    $this
        ->actingAs($this->user, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'published_at'
        );
});

it('muestra una noticia existente', function () {
    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->getJson(
            "/api/admin/news/{$news->id}"
        )
        ->assertOk();
});

it('actualiza una noticia', function () {
    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'title' => 'Título actualizado',
                'excerpt' => 'Resumen actualizado.',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'title' => 'Título actualizado',
        'excerpt' => 'Resumen actualizado.',
        'updated_by' => $this->user->id,
    ]);
});

it('no modifica el slug cuando cambia el título', function () {
    $news = createNewsForNewsTest(
        $this->user,
        'Título original',
        'titulo-original'
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'title' => 'Título completamente nuevo',
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'title' => 'Título completamente nuevo',
        'slug' => 'titulo-original',
    ]);
});

it('impide publicar mediante actualización sin news.publish', function () {
    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'status' => 'published',
                'published_at' => now()
                    ->toDateString(),
            ]
        )
        ->assertForbidden();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'status' => 'draft',
    ]);
});

it('permite publicar mediante actualización con news.publish', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'status' => 'published',
                'published_at' => now()
                    ->toDateString(),
            ]
        )
        ->assertOk();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'status' => 'published',
        'updated_by' => $this->user->id,
    ]);
});

it('rechaza publicar mediante actualización sin fecha de publicación', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'status' => 'published',
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'published_at'
        );

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'status' => 'draft',
        'published_at' => null,
    ]);
});

it('impide quitar la fecha a una noticia que ya está publicada', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $news = createNewsForNewsTest(
        $this->user,
        'Noticia publicada',
        'noticia-publicada',
        'published'
    );

    $originalPublishedAt = $news
        ->published_at
        ->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'published_at' => null,
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors(
            'published_at'
        );

    $news->refresh();

    expect($news->status->value)
        ->toBe('published');

    expect(
        $news->published_at->toDateString()
    )->toBe($originalPublishedAt);
});

it('permite actualizar otros campos de una noticia publicada conservando su fecha', function () {
    $this->newsRole->givePermissionTo(
        'news.publish'
    );

    $news = createNewsForNewsTest(
        $this->user,
        'Título publicado',
        'titulo-publicado',
        'published'
    );

    $originalPublishedAt = $news
        ->published_at
        ->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'title' => 'Título publicado actualizado',
            ]
        )
        ->assertOk();

    $news->refresh();

    expect($news->status->value)
        ->toBe('published');

    expect(
        $news->published_at->toDateString()
    )->toBe($originalPublishedAt);

    expect($news->title)
        ->toBe('Título publicado actualizado');
});

it('elimina una noticia mediante soft delete', function () {
    $news = createNewsForNewsTest(
        $this->user
    );

    $this
        ->actingAs($this->user, 'api')
        ->deleteJson(
            "/api/admin/news/{$news->id}"
        )
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Noticia eliminada correctamente.',
        ]);

    $this->assertSoftDeleted('news', [
        'id' => $news->id,
    ]);
});
