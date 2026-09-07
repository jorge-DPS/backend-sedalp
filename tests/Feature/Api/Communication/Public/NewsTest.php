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
        'description' => "Descripción de {$title}",
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
        'noticia-publica'
    );

    $this->getJson('/api/news')
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
        ->assertJsonPath('data.0.slug', 'noticia-publica')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonMissingPath('data.0.createdBy');
});

it('solo expone noticias públicamente visibles', function () {
    createPublicNewsForTest(
        $this->creator,
        'Publicada hoy',
        'publicada-hoy'
    );

    createPublicNewsForTest(
        $this->creator,
        'Borrador',
        'borrador',
        'draft',
        null
    );

    createPublicNewsForTest(
        $this->creator,
        'Archivada',
        'archivada',
        'archived',
        null
    );

    createPublicNewsForTest(
        $this->creator,
        'Publicación futura',
        'publicacion-futura',
        'published',
        now()->addDay()->toDateString()
    );

    $deleted = createPublicNewsForTest(
        $this->creator,
        'Eliminada',
        'eliminada'
    );
    $deleted->delete();

    $this->getJson('/api/news')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'publicada-hoy');
});

it('ordena las noticias por fecha de publicación descendente', function () {
    createPublicNewsForTest(
        $this->creator,
        'Noticia anterior',
        'noticia-anterior',
        'published',
        now()->subDays(2)->toDateString()
    );

    createPublicNewsForTest(
        $this->creator,
        'Noticia reciente',
        'noticia-reciente',
        'published',
        now()->toDateString()
    );

    $this->getJson('/api/news')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'noticia-reciente')
        ->assertJsonPath('data.1.slug', 'noticia-anterior');
});

it('permite buscar noticias públicas', function () {
    createPublicNewsForTest(
        $this->creator,
        'Agua potable para La Paz',
        'agua-potable-la-paz'
    );

    createPublicNewsForTest(
        $this->creator,
        'Otra noticia institucional',
        'otra-noticia-institucional'
    );

    $this->getJson('/api/news?search=Agua')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'agua-potable-la-paz');
});

it('limita la paginación pública a treinta elementos por página', function () {
    $this->getJson('/api/news?per_page=31')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('devuelve la imagen de portada en el listado', function () {
    $news = createPublicNewsForTest(
        $this->creator,
        'Noticia con imágenes',
        'noticia-con-imagenes'
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

    $this->getJson('/api/news')
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
        'detalle-publico'
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

    $this->getJson('/api/news/detalle-publico')
        ->assertOk()
        ->assertJsonPath('data.slug', 'detalle-publico')
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
        'borrador-secreto',
        'draft',
        null
    );

    $this->getJson('/api/news/borrador-secreto')
        ->assertNotFound();
});

it('no permite consultar una publicación futura mediante su slug', function () {
    createPublicNewsForTest(
        $this->creator,
        'Noticia futura',
        'noticia-futura',
        'published',
        now()->addDay()->toDateString()
    );

    $this->getJson('/api/news/noticia-futura')
        ->assertNotFound();
});
