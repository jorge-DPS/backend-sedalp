<?php

use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use App\Models\Communication\NewsVideo;
use App\Models\User;

beforeEach(function () {
    $this->creator = User::factory()->create();
});

function createPublicNewsForTest(
    User $creator,
    string $title,
    string $slug,
    string $status = 'published',
    ?string $publishedAt = null
): News {
    $news = new News;

    $news->fill([
        'title' => $title,
        'subtitle' => "Subtítulo de {$title}",
        'excerpt' => "Resumen de {$title}",
        'content' => [
            'type' => 'doc',
            'content' => [],
        ],
        'status' => $status,
        'published_at' => $status === 'published'
            ? ($publishedAt ?? now()->toDateString())
            : $publishedAt,
    ]);

    $news->slug = $slug;
    $news->created_by = $creator->id;
    $news->save();

    return $news;
}

it('lista noticias publicadas sin autenticación', function () {
    createPublicNewsForTest(
        $this->creator,
        'Noticia pública',
        '200000000014'
    );

    $this->getJson('/api/public/news')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'slug',
                    'title',
                    'subtitle',
                    'excerpt',
                    'publishedAt',
                    'coverImage',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonPath('data.0.slug', '200000000014')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonMissingPath('data.0.createdBy');
});

it('solo expone noticias públicamente visibles', function () {
    createPublicNewsForTest(
        $this->creator,
        'Publicada hoy',
        '200000000015'
    );

    createPublicNewsForTest(
        $this->creator,
        'Borrador',
        '200000000016',
        'draft',
        null
    );

    createPublicNewsForTest(
        $this->creator,
        'Archivada',
        '200000000017',
        'archived',
        null
    );

    createPublicNewsForTest(
        $this->creator,
        'Publicación futura',
        '200000000018',
        'published',
        now()->addDay()->toDateString()
    );

    $deleted = createPublicNewsForTest(
        $this->creator,
        'Eliminada',
        '200000000019'
    );
    $deleted->delete();

    $this->getJson('/api/public/news')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', '200000000015');
});

it('ordena las noticias por fecha de publicación descendente', function () {
    createPublicNewsForTest(
        $this->creator,
        'Noticia anterior',
        '200000000020',
        'published',
        now()->subDays(2)->toDateString()
    );

    createPublicNewsForTest(
        $this->creator,
        'Noticia reciente',
        '200000000021',
        'published',
        now()->toDateString()
    );

    $this->getJson('/api/public/news')
        ->assertOk()
        ->assertJsonPath('data.0.slug', '200000000021')
        ->assertJsonPath('data.1.slug', '200000000020');
});

it('permite buscar noticias públicas', function () {
    createPublicNewsForTest(
        $this->creator,
        'Agua potable para La Paz',
        '200000000022'
    );

    createPublicNewsForTest(
        $this->creator,
        'Otra noticia institucional',
        '200000000023'
    );

    $this->getJson('/api/public/news?search=Agua')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', '200000000022');
});

it('limita la paginación pública a treinta elementos por página', function () {
    $this->getJson('/api/public/news?per_page=31')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('devuelve la imagen de portada en el listado', function () {
    $news = createPublicNewsForTest(
        $this->creator,
        'Noticia con imágenes',
        '200000000024'
    );

    NewsImage::query()->create([
        'news_id' => $news->id,
        'filename' => 'segunda-imagen',
        'alt' => 'Segunda imagen',
        'caption' => null,
        'position' => 1,
    ]);

    NewsImage::query()->create([
        'news_id' => $news->id,
        'filename' => 'imagen-portada',
        'alt' => 'Imagen de portada',
        'caption' => 'Portada',
        'position' => 0,
    ]);

    $this->getJson('/api/public/news')
        ->assertOk()
        ->assertJsonPath(
            'data.0.coverImage.filename',
            'imagen-portada'
        )
        ->assertJsonPath(
            'data.0.coverImage.position',
            0
        )
        ->assertJsonMissingPath('data.0.coverImage.id');
});

it('muestra el detalle de una noticia publicada mediante slug', function () {
    $news = createPublicNewsForTest(
        $this->creator,
        'Detalle público',
        '200000000025'
    );

    NewsImage::query()->create([
        'news_id' => $news->id,
        'filename' => 'imagen-detalle',
        'alt' => 'Imagen de detalle',
        'caption' => null,
        'position' => 0,
    ]);

    NewsVideo::query()->create([
        'news_id' => $news->id,
        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title' => 'Video de detalle',
        'position' => 0,
    ]);

    $this->getJson('/api/public/news/200000000025')
        ->assertOk()
        ->assertJsonPath('data.slug', '200000000025')
        ->assertJsonPath('data.excerpt', $news->excerpt)
        ->assertJsonPath('data.content.type', 'doc')
        ->assertJsonMissingPath('data.description')
        ->assertJsonPath('data.images.0.filename', 'imagen-detalle')
        ->assertJsonPath(
            'data.videos.0.youtubeUrl',
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
        )
        ->assertJsonMissingPath('data.status')
        ->assertJsonMissingPath('data.createdBy')
        ->assertJsonMissingPath('data.updatedBy');
});

it('no permite consultar por slug una noticia que no sea pública', function () {
    createPublicNewsForTest(
        $this->creator,
        'Borrador secreto',
        '200000000026',
        'draft',
        null
    );

    $this->getJson('/api/public/news/200000000026')
        ->assertNotFound();
});

it('no permite consultar una publicación futura mediante su slug', function () {
    createPublicNewsForTest(
        $this->creator,
        'Noticia futura',
        '200000000027',
        'published',
        now()->addDay()->toDateString()
    );

    $this->getJson('/api/public/news/200000000027')
        ->assertNotFound();
});
