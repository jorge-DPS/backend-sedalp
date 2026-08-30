<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ReorderNewsMediaRequest;
use App\Http\Requests\Communication\StoreNewsVideoRequest;
use App\Http\Requests\Communication\UpdateNewsVideoRequest;
use App\Http\Resources\Communication\NewsVideoResource;
use App\Models\Communication\News;
use App\Models\Communication\NewsVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NewsVideoController extends Controller
{
  public function store(
    StoreNewsVideoRequest $request,
    News $news
  ): NewsVideoResource {
    $video = DB::transaction(function () use (
      $request,
      $news
    ) {
      /*
         * Bloqueamos esta noticia mientras calculamos
         * y asignamos la siguiente posición.
         */
      $this->lockNewsForMediaMutation($news);

      $position = (
        $news->videos()
        ->max('position') ?? -1
      ) + 1;

      $video = $news->videos()->create([
        ...$request->validated(),
        'position' => $position,
      ]);

      $news->updated_by = $request
        ->user('api')
        ->id;

      $news->save();

      return $video;
    });

    return new NewsVideoResource($video);
  }

  public function update(
    UpdateNewsVideoRequest $request,
    News $news,
    NewsVideo $video
  ): NewsVideoResource {
    DB::transaction(function () use (
      $request,
      $news,
      $video
    ) {
      $video->update(
        $request->validated()
      );

      $news->updated_by = $request
        ->user('api')
        ->id;

      $news->save();
    });

    return new NewsVideoResource($video);
  }

  public function destroy(
    News $news,
    NewsVideo $video
  ): JsonResponse {
    DB::transaction(function () use (
      $news,
      $video
    ) {
      $this->lockNewsForMediaMutation($news);
      $video->delete();

      $this->normalizePositions($news);

      $news->updated_by = auth('api')->id();
      $news->save();
    });

    return response()->json([
      'success' => true,
      'message' => 'Video eliminado correctamente.',
    ]);
  }

  public function reorder(
    ReorderNewsMediaRequest $request,
    News $news
  ) {
    $items = collect(
      $request->validated('items')
    );

    $requestIds = $items
      ->pluck('id')
      ->sort()
      ->values();

    $positions = $items
      ->pluck('position')
      ->sort()
      ->values()
      ->all();

    $expectedPositions = range(
      0,
      $items->count() - 1
    );

    abort_unless(
      $positions === $expectedPositions,
      422,
      'Las posiciones deben ser consecutivas desde 0.'
    );

    DB::transaction(function () use (
      $request,
      $news,
      $items,
      $requestIds
    ) {
      $this->lockNewsForMediaMutation($news);

      $currentIds = $news
        ->videos()
        ->pluck('id')
        ->sort()
        ->values();

      abort_unless(
        $currentIds->all() === $requestIds->all(),
        422,
        'Debe enviar todos los videos de la noticia.'
      );

      foreach ($items->values() as $index => $item) {
        NewsVideo::whereKey($item['id'])
          ->update([
            'position' => 1_000_000 + $index,
          ]);
      }

      foreach ($items as $item) {
        NewsVideo::whereKey($item['id'])
          ->update([
            'position' => $item['position'],
          ]);
      }

      $news->updated_by = $request
        ->user('api')
        ->id;

      $news->save();
    });

    return NewsVideoResource::collection(
      $news->fresh()->videos
    );
  }

  private function normalizePositions(
    News $news
  ): void {
    $news->videos()
      ->orderBy('position')
      ->get()
      ->each(function (
        NewsVideo $video,
        int $position
      ) {
        $video->update([
          'position' => $position,
        ]);
      });
  }

  private function lockNewsForMediaMutation(
    News $news
  ): void {
    News::query()
      ->whereKey($news->id)
      ->lockForUpdate()
      ->firstOrFail();
  }
}
