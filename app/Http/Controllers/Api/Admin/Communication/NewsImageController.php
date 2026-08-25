<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ReorderNewsMediaRequest;
use App\Http\Requests\Communication\StoreNewsImagesRequest;
use App\Http\Requests\Communication\UpdateNewsImageRequest;
use App\Http\Resources\Communication\NewsImageResource;
use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class NewsImageController extends Controller
{
    public function store(
        StoreNewsImagesRequest $request,
        News $news
    ) {
        $validated = $request->validated();

        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $news,
                $validated,
                &$storedPaths
            ) {
                $position = (
                    $news->images()->max('position') ?? -1
                ) + 1;

                foreach ($validated['images'] as $imageData) {
                    $path = Storage::disk('public')
                        ->putFile(
                            "news/{$news->id}",
                            $imageData['file']
                        );

                    if ($path === false) {
                        throw new \RuntimeException(
                            'No se pudo almacenar la imagen.'
                        );
                    }

                    $storedPaths[] = $path;

                    $news->images()->create([
                        'path' => $path,
                        'alt' => $imageData['alt'],
                        'caption' => $imageData['caption'] ?? null,
                        'position' => $position++,
                    ]);
                }

                $news->updated_by = $request
                    ->user('api')
                    ->id;

                $news->save();
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('public')
                    ->delete($storedPaths);
            }

            throw $exception;
        }

        return NewsImageResource::collection(
            $news->fresh()->images
        );
    }

    public function update(
        UpdateNewsImageRequest $request,
        News $news,
        NewsImage $image
    ): NewsImageResource {
        $image->update(
            $request->validated()
        );

        $news->updated_by = $request
            ->user('api')
            ->id;

        $news->save();

        return new NewsImageResource($image);
    }

    public function destroy(
        News $news,
        NewsImage $image
    ): JsonResponse {
        $path = $image->path;

        DB::transaction(function () use (
            $news,
            $image
        ) {
            $image->delete();

            $this->normalizePositions($news);

            $news->updated_by = auth('api')->id();
            $news->save();
        });

        Storage::disk('public')->delete($path);

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
        ]);
    }

    public function reorder(
        ReorderNewsMediaRequest $request,
        News $news
    ) {
        $items = collect(
            $request->validated('items')
        );

        $currentIds = $news
            ->images()
            ->pluck('id')
            ->sort()
            ->values();

        $requestIds = $items
            ->pluck('id')
            ->sort()
            ->values();

        abort_unless(
            $currentIds->all() === $requestIds->all(),
            422,
            'Debe enviar todas las imágenes de la noticia.'
        );

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
            $items
        ) {
            /*
             * Posiciones temporales para evitar
             * conflicto con UNIQUE(news_id, position).
             */
            foreach ($items->values() as $index => $item) {
                NewsImage::whereKey($item['id'])
                    ->update([
                        'position' => 1_000_000 + $index,
                    ]);
            }

            foreach ($items as $item) {
                NewsImage::whereKey($item['id'])
                    ->update([
                        'position' => $item['position'],
                    ]);
            }

            $news->updated_by = $request
                ->user('api')
                ->id;

            $news->save();
        });

        return NewsImageResource::collection(
            $news->fresh()->images
        );
    }

    private function normalizePositions(
        News $news
    ): void {
        $news->images()
            ->orderBy('position')
            ->get()
            ->each(function (
                NewsImage $image,
                int $position
            ) {
                $image->update([
                    'position' => $position,
                ]);
            });
    }
}
