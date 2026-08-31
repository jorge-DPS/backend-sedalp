<?php

use App\Models\Communication\News;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Storage::fake(
        config('media.disk', 'public')
    );

    $this->trashRole = Role::create([
        'name' => 'news_trash_test',
        'guard_name' => 'api',
    ]);

    $this->trashRole->givePermissionTo([
        'news.trash.view',
        'news.restore',
        'news.force_delete',
    ]);

    $this->admin = User::factory()->create([
        'email' => 'trash.admin@test.com',
    ]);

    $this->admin->assignRole($this->trashRole);
});

function createNewsForTrashTest(
    User $creator,
    string $title = 'Noticia eliminada',
    string $slug = 'noticia-eliminada',
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

it('rechaza consultar la papelera sin autenticación', function () {
    $this
        ->getJson('/api/admin/news/trash')
        ->assertUnauthorized();
});

it('rechaza consultar la papelera sin permiso', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/news/trash')
        ->assertForbidden();
});

it('permite consultar la papelera con news.trash.view', function () {
    $news = createNewsForTrashTest(
        $this->admin
    );

    $news->delete();

    $response = $this
        ->actingAs($this->admin, 'api')
        ->getJson('/api/admin/news/trash');

    $response
        ->assertOk()
        ->assertJsonPath(
            'data.0.id',
            $news->id
        );
});

it('la papelera solo muestra noticias eliminadas', function () {
    createNewsForTrashTest(
        $this->admin,
        'Noticia activa',
        'noticia-activa'
    );

    $deleted = createNewsForTrashTest(
        $this->admin,
        'Noticia en papelera',
        'noticia-en-papelera'
    );

    $deleted->delete();

    $response = $this
        ->actingAs($this->admin, 'api')
        ->getJson('/api/admin/news/trash');

    $response->assertOk();

    $ids = collect(
        $response->json('data')
    )->pluck('id');

    expect($ids)
        ->toContain($deleted->id);

    expect($ids)
        ->not->toContain(
            News::where(
                'slug',
                'noticia-activa'
            )->value('id')
        );
});

it('el comunicador no puede consultar la papelera', function () {
    $user = User::factory()->create([
        'email' => 'comunicador@test.com',
    ]);

    $user->assignRole('comunicador');

    $this
        ->actingAs($user, 'api')
        ->getJson('/api/admin/news/trash')
        ->assertForbidden();
});

it('restaura una noticia eliminada', function () {
    $news = createNewsForTrashTest(
        $this->admin
    );

    $news->delete();

    $this->assertSoftDeleted('news', [
        'id' => $news->id,
    ]);

    $this
        ->actingAs($this->admin, 'api')
        ->postJson(
            "/api/admin/news/{$news->id}/restore"
        )
        ->assertOk();

    $this->assertDatabaseHas('news', [
        'id' => $news->id,
        'deleted_at' => null,
        'updated_by' => $this->admin->id,
    ]);

    expect(
        News::find($news->id)
    )->not->toBeNull();
});

it('conserva el estado published al restaurar una noticia', function () {
    $news = createNewsForTrashTest(
        $this->admin,
        'Noticia publicada',
        'noticia-publicada',
        'published'
    );

    $news->delete();

    $this
        ->actingAs($this->admin, 'api')
        ->postJson(
            "/api/admin/news/{$news->id}/restore"
        )
        ->assertOk();

    $restored = News::findOrFail(
        $news->id
    );

    expect($restored->status->value)
        ->toBe('published');

    expect($restored->published_at)
        ->not->toBeNull();
});

it('rechaza restaurar si no tiene news.restore', function () {
    $role = Role::create([
        'name' => 'trash_view_only_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo(
        'news.trash.view'
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    $news = createNewsForTrashTest(
        $this->admin
    );

    $news->delete();

    $this
        ->actingAs($user, 'api')
        ->postJson(
            "/api/admin/news/{$news->id}/restore"
        )
        ->assertForbidden();

    $this->assertSoftDeleted('news', [
        'id' => $news->id,
    ]);
});

it('elimina definitivamente una noticia', function () {
    $news = createNewsForTrashTest(
        $this->admin
    );

    $news->delete();

    $this
        ->actingAs($this->admin, 'api')
        ->deleteJson(
            "/api/admin/news/{$news->id}/force"
        )
        ->assertSuccessful();

    $this->assertDatabaseMissing('news', [
        'id' => $news->id,
    ]);

    expect(
        News::withTrashed()
            ->find($news->id)
    )->toBeNull();
});

it('el force delete elimina imágenes y videos mediante cascade', function () {
    $news = createNewsForTrashTest(
        $this->admin
    );

    $image = $news->images()->create([
        'filename' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        'alt' => 'Imagen para force delete',
        'caption' => null,
        'position' => 0,
    ]);

    $video = $news->videos()->create([
        'youtube_url' => 'https://www.youtube.com/watch?v=forceDelete',
        'title' => 'Video para force delete',
        'position' => 0,
    ]);

    $news->delete();

    /*
     * El soft delete de la noticia NO debe borrar
     * todavía sus relaciones.
     */
    $this->assertDatabaseHas('news_images', [
        'id' => $image->id,
    ]);

    $this->assertDatabaseHas('news_videos', [
        'id' => $video->id,
    ]);

    $this
        ->actingAs($this->admin, 'api')
        ->deleteJson(
            "/api/admin/news/{$news->id}/force"
        )
        ->assertSuccessful();

    /*
     * Al hacer force delete, las FK de PostgreSQL
     * deben aplicar ON DELETE CASCADE.
     */
    $this->assertDatabaseMissing('news', [
        'id' => $news->id,
    ]);

    $this->assertDatabaseMissing('news_images', [
        'id' => $image->id,
    ]);

    $this->assertDatabaseMissing('news_videos', [
        'id' => $video->id,
    ]);
});

it('rechaza force delete sin news.force_delete', function () {
    $role = Role::create([
        'name' => 'trash_restore_only_test',
        'guard_name' => 'api',
    ]);

    $role->givePermissionTo([
        'news.trash.view',
        'news.restore',
    ]);

    $user = User::factory()->create();
    $user->assignRole($role);

    $news = createNewsForTrashTest(
        $this->admin
    );

    $news->delete();

    $this
        ->actingAs($user, 'api')
        ->deleteJson(
            "/api/admin/news/{$news->id}/force"
        )
        ->assertForbidden();

    expect(
        News::withTrashed()
            ->find($news->id)
    )->not->toBeNull();
});

it('el comunicador no recibe permisos administrativos de papelera', function () {
    $communicator = Role::findByName(
        'comunicador',
        'api'
    );

    expect(
        $communicator->hasPermissionTo(
            'news.trash.view'
        )
    )->toBeFalse();

    expect(
        $communicator->hasPermissionTo(
            'news.restore'
        )
    )->toBeFalse();

    expect(
        $communicator->hasPermissionTo(
            'news.force_delete'
        )
    )->toBeFalse();
});
