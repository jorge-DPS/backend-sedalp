<?php

use App\Models\Communication\News;
use App\Models\User;
use App\Services\Communication\NewsService;
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
    string $slug = '200000000001',
    string $status = 'draft'
): News {
    $news = new News;

    $news->fill([
        'title' => $title,
        'subtitle' => null,
        'excerpt' => 'Resumen de prueba.',
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
        '200000000002'
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

    $response->assertCreated()
        ->assertJsonPath('data.excerpt', $this->validNewsData['excerpt'])
        ->assertJsonPath('data.content.type', 'doc')
        ->assertJsonMissingPath('data.description');

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => $response->json('data.slug'),
        'status' => 'draft',
        'created_by' => $this->user->id,
    ]);
});

it('genera automáticamente un código numérico de doce dígitos', function () {
    $response = $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/news',
            $this->validNewsData
        )
        ->assertCreated();

    expect($response->json('data.slug'))->toBeString()->toMatch('/^[1-9][0-9]{11}$/');

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => $response->json('data.slug'),
    ]);
});

it('reintenta si el código ya existe incluso en la papelera', function (bool $trashed) {
    $existingNews = createNewsForNewsTest(
        $this->user,
        'Nueva obra para La Paz',
        '100000000001'
    );

    if ($trashed) {
        $existingNews->delete();
    }

    $service = Mockery::mock(NewsService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('generateSlugCandidate')->twice()->andReturn('100000000001', '100000000002');
    $this->app->instance(NewsService::class, $service);

    $this
        ->actingAs($this->user, 'api')
        ->postJson(
            '/api/admin/news',
            $this->validNewsData
        )
        ->assertCreated()
        ->assertJsonPath('data.slug', '100000000002');

    $this->assertDatabaseHas('news', [
        'title' => 'Nueva obra para La Paz',
        'slug' => '100000000002',
    ]);
})->with([false, true]);

it('genera códigos distintos para noticias con el mismo título', function () {
    $this->actingAs($this->user, 'api');
    $first = $this->postJson('/api/admin/news', $this->validNewsData)->assertCreated()->json('data.slug');
    $second = $this->postJson('/api/admin/news', $this->validNewsData)->assertCreated()->json('data.slug');

    expect($first)->not->toBe($second);
});

it('el cliente no puede elegir ni modificar el código de la noticia', function () {
    $this->actingAs($this->user, 'api');
    $response = $this->postJson('/api/admin/news', [
        ...$this->validNewsData,
        'slug' => 'codigo-elegido',
    ])->assertCreated();

    $slug = $response->json('data.slug');
    expect($slug)->toMatch('/^[1-9][0-9]{11}$/');

    $this->patchJson('/api/admin/news/'.$response->json('data.id'), [
        'title' => 'Título editado',
        'slug' => '999999999999',
    ])->assertOk()->assertJsonPath('data.slug', $slug);
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
        '200000000003'
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
        'slug' => '200000000003',
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
        '200000000004',
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
        '200000000005',
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

it('impide cambiar la fecha de una noticia publicada sin news.publish', function () {
    $news = createNewsForNewsTest(
        $this->user,
        'Noticia publicada protegida',
        '200000000006',
        'published'
    );

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'published_at' => now()
                    ->addDay()
                    ->toDateString(),
            ]
        )
        ->assertForbidden();
});

it('permite cambiar la fecha de publicación con news.publish', function () {
    $this->newsRole->givePermissionTo('news.publish');

    $news = createNewsForNewsTest(
        $this->user,
        'Noticia publicada con fecha editable',
        '200000000007',
        'published'
    );

    $newPublishedAt = now()
        ->addDay()
        ->toDateString();

    $this
        ->actingAs($this->user, 'api')
        ->patchJson(
            "/api/admin/news/{$news->id}",
            [
                'published_at' => $newPublishedAt,
            ]
        )
        ->assertOk()
        ->assertJsonPath('data.publishedAt', $newPublishedAt);
});

it('conserva el autor cuando su usuario fue eliminado lógicamente', function () {
    $news = createNewsForNewsTest(
        $this->user,
        'Noticia con autor eliminado',
        '200000000008'
    );

    $viewer = User::factory()->create([
        'email' => 'news.viewer@test.com',
    ]);
    $viewer->assignRole($this->newsRole);

    $this->user->delete();

    $this
        ->actingAs($viewer, 'api')
        ->getJson("/api/admin/news/{$news->id}")
        ->assertOk()
        ->assertJsonPath('data.createdBy.id', $this->user->id)
        ->assertJsonPath(
            'data.createdBy.email',
            'editor.noticias@test.com'
        );
});

it('permite al superadmin realizar el CRUD de noticias', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin.news@test.com',
    ]);
    $superAdmin->assignRole('super_admin');

    $data = $this->validNewsData;
    $data['title'] = 'Noticia del superadministrador';

    $newsId = $this
        ->actingAs($superAdmin, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertCreated()
        ->json('data.id');

    $this
        ->actingAs($superAdmin, 'api')
        ->getJson('/api/admin/news')
        ->assertOk();

    $this
        ->actingAs($superAdmin, 'api')
        ->patchJson(
            "/api/admin/news/{$newsId}",
            ['title' => 'Noticia del superadministrador actualizada']
        )
        ->assertOk();

    $this
        ->actingAs($superAdmin, 'api')
        ->deleteJson("/api/admin/news/{$newsId}")
        ->assertOk();

    $this->assertSoftDeleted('news', ['id' => $newsId]);
});

it('permite al comunicador realizar únicamente el CRUD de noticias', function () {
    $communicator = User::factory()->create([
        'email' => 'comunicador.crud@test.com',
    ]);
    $communicator->assignRole('comunicador');

    $data = $this->validNewsData;
    $data['title'] = 'Noticia del comunicador';
    $data['status'] = 'published';
    $data['published_at'] = now()->toDateString();

    $newsId = $this
        ->actingAs($communicator, 'api')
        ->postJson('/api/admin/news', $data)
        ->assertCreated()
        ->assertJsonPath('data.status', 'published')
        ->json('data.id');

    $this
        ->actingAs($communicator, 'api')
        ->getJson("/api/admin/news/{$newsId}")
        ->assertOk();

    $this
        ->actingAs($communicator, 'api')
        ->patchJson(
            "/api/admin/news/{$newsId}",
            ['title' => 'Noticia del comunicador actualizada']
        )
        ->assertOk();

    $this
        ->actingAs($communicator, 'api')
        ->deleteJson("/api/admin/news/{$newsId}")
        ->assertOk();

    $this
        ->actingAs($communicator, 'api')
        ->getJson('/api/admin/users')
        ->assertForbidden();
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
