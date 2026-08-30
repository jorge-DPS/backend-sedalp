<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Enums\Communication\NewsStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\IndexNewsRequest;
use App\Http\Requests\Communication\StoreNewsRequest;
use App\Http\Requests\Communication\UpdateNewsRequest;
use App\Http\Resources\Communication\NewsResource;
use App\Models\Communication\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsController extends Controller
{
  public function index(IndexNewsRequest $request)
  {
    $validated = $request->validated();

    $query = News::query()
      ->with([
        'images',
        'videos',
        'creator:id,email',
        'updater:id,email',
      ])
      ->search($validated['search'] ?? null);

    if (! empty($validated['status'])) {
      $query->where(
        'status',
        $validated['status']
      );
    }

    $news = $query
      ->orderByDesc('created_at')
      ->paginate(
        $validated['per_page'] ?? 15
      )
      ->withQueryString();

    return NewsResource::collection($news);
  }

  public function store(
    StoreNewsRequest $request
  ): NewsResource {
    $validated = $request->validated();

    $status = $validated['status']
      ?? NewsStatus::DRAFT->value;

    if ($status === NewsStatus::PUBLISHED->value) {
      abort_unless(
        $request->user('api')->can('news.publish'),
        403,
        'No tiene permiso para publicar noticias.'
      );
    }

    $news = DB::transaction(function () use (
      $request,
      $validated,
      $status
    ) {
      /*
         * Obtenemos primero el slug base.
         *
         * Ejemplo:
         * "Nueva obra para La Paz"
         *        ↓
         * "nueva-obra-para-la-paz"
         */
      $baseSlug = $this->generateBaseSlug(
        $validated['title']
      );

      /*
         * Bloqueamos únicamente la generación de slugs
         * con el mismo slug base.
         *
         * Si llegan dos peticiones simultáneas para:
         *
         * nueva-obra
         *
         * una esperará a que la otra termine.
         *
         * El lock se libera automáticamente al finalizar
         * esta transacción.
         */
      DB::selectOne(
        <<<'SQL'
            SELECT pg_advisory_xact_lock(
                hashtext('news_slug'),
                hashtext(?)
            )
            SQL,
        [$baseSlug]
      );

      /*
         * Ahora que tenemos el lock podemos comprobar
         * de forma segura qué slug está disponible.
         */
      $slug = $this->generateUniqueSlug(
        $baseSlug
      );

      $news = new News();

      $news->fill($validated);

      $news->slug = $slug;

      $news->status = $status;

      $news->created_by = $request
        ->user('api')
        ->id;

      $news->save();

      return $news;
    });

    $news->load([
      'images',
      'videos',
      'creator:id,email',
      'updater:id,email',
    ]);


    return new NewsResource($news);
  }

  public function show(News $news): NewsResource
  {
    $news->load([
      'images',
      'videos',
      'creator:id,email',
      'updater:id,email',
    ]);

    return new NewsResource($news);
  }

  public function update(UpdateNewsRequest $request, News $news): NewsResource
  {
    $validated = $request->validated();

    if (array_key_exists('status', $validated)) {
      $currentStatus = $news->status->value;
      $newStatus = $validated['status'];

      $publicationChanged =
        $currentStatus !== $newStatus
        && (
          $currentStatus === NewsStatus::PUBLISHED->value
          || $newStatus === NewsStatus::PUBLISHED->value
        );

      if ($publicationChanged) {
        abort_unless(
          $request->user('api')->can('news.publish'),
          403,
          'No tiene permiso para cambiar el estado de publicación.'
        );
      }
    }

    DB::transaction(function () use (
      $request,
      $news,
      $validated
    ) {
      $news->fill($validated);

      /*
             * No regeneramos automáticamente el slug
             * cuando cambia el título.
             *
             * De esta manera no rompemos URLs públicas.
             */

      $news->updated_by = $request
        ->user('api')
        ->id;

      $news->save();
    });

    $news->load([
      'images',
      'videos',
      'creator:id,email',
      'updater:id,email',
    ]);

    return new NewsResource($news);
  }

  public function destroy(
    News $news
  ): JsonResponse {
    DB::transaction(function () use ($news) {
      $news->updated_by = auth('api')->id();
      $news->save();

      $news->delete();
    });

    return response()->json([
      'success' => true,
      'message' => 'Noticia eliminada correctamente.',
    ]);
  }

  private function generateBaseSlug(
    string $title
  ): string {
    $baseSlug = Str::slug($title);

    if (blank($baseSlug)) {
      return 'noticia';
    }

    return $baseSlug;
  }

  private function generateUniqueSlug(
    string $baseSlug
  ): string {
    $slug = $baseSlug;
    $counter = 2;

    while (
      News::withTrashed()
      ->where('slug', $slug)
      ->exists()
    ) {
      $slug = "{$baseSlug}-{$counter}";
      $counter++;
    }

    return $slug;
  }


}
