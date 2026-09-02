<?php

namespace App\Http\Controllers\Api\Admin\Communication;

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ReorderNewsMediaRequest;
use App\Http\Requests\Communication\StoreNewsImagesRequest;
use App\Http\Requests\Communication\UpdateNewsImageRequest;
use App\Http\Resources\Communication\NewsImageResource;
use App\Jobs\Media\CleanupImageFiles;
use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use App\Services\Media\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsImageController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function store(
        StoreNewsImagesRequest $request,
        News $news
    ): AnonymousResourceCollection {
        $validated = $request->validated();

        $createdImages = [];

        $options = new ImageOptions(

            /*
                   * Vue NO manda esto.
                   *
                   * Laravel decide dónde guardar.
                   */
            directory: NewsImage::MEDIA_DIRECTORY,

            width: 1920,

            resizeMode: ImageResizeMode::SCALE_DOWN,

            formats: [
                ImageFormat::WEBP,
                ImageFormat::PNG,
                ImageFormat::JPEG,
            ],

            webpQuality: 80,

            jpegQuality: 82,

            jpegProgressive: true,
        );

        try {
            $storedImages = collect($validated['images'])
                ->map(function (array $imageData) use (
                    $options,
                    &$createdImages
                ): array {
                    $filename = $this->imageService->store(
                        file: $imageData['file'],
                        options: $options,
                    );

                    $createdImages[] = $filename;

                    return [
                        'filename' => $filename,
                        'alt' => $imageData['alt'],
                        'caption' => $imageData['caption'] ?? null,
                    ];
                });

            DB::transaction(function () use (
                $request,
                $news,
                $storedImages
            ): void {
                $this->lockNewsForMediaMutation($news);

                $position = (
                    $news->images()
                        ->max('position') ?? -1
                ) + 1;

                foreach ($storedImages as $storedImage) {
                    $news->images()->create([
                        ...$storedImage,
                        'position' => $position++,
                    ]);
                }

                $news->updated_by = $request
                    ->user('api')
                    ->id;

                $news->save();
            });
        } catch (Throwable $exception) {

            /*
                   * PostgreSQL hace rollback,
                   * pero Storage no.
                   *
                   * Por eso debemos limpiar
                   * las imágenes generadas.
                   */
            foreach ($createdImages as $filename) {
                try {
                    $this->imageService->delete(
                        filename: $filename,
                        directory: NewsImage::MEDIA_DIRECTORY,
                    );
                } catch (Throwable $cleanupException) {
                    Log::error(
                        'No se pudo limpiar una imagen después de un rollback.',
                        [
                            'news_id' => $news->id,
                            'filename' => $filename,
                            'exception' => $cleanupException->getMessage(),
                        ]
                    );

                    CleanupImageFiles::dispatch(
                        filename: $filename,
                        directory: NewsImage::MEDIA_DIRECTORY,
                    );
                }
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
        DB::transaction(function () use (
            $request,
            $news,
            $image
        ): void {
            $image->update(
                $request->validated()
            );

            $news->updated_by = $request
                ->user('api')
                ->id;

            $news->save();
        });

        return new NewsImageResource($image);
    }

    public function destroy(
        News $news,
        NewsImage $image
    ): JsonResponse {
        /*
         * Protección adicional:
         * la imagen debe pertenecer a la noticia
         * indicada en la URL.
         */
        abort_unless(
            $image->news_id === $news->id,
            404
        );

        $filename = $image->filename;

        DB::transaction(function () use (
            $news,
            $image
        ): void {
            $this->lockNewsForMediaMutation($news);

            $image->delete();

            $this->normalizePositions($news);

            $news->updated_by = auth('api')->id();
            $news->save();
        });

        /*
         * PostgreSQL ya confirmó la eliminación
         * y normalización de posiciones.
         *
         * Intentamos eliminar los archivos físicos
         * inmediatamente.
         *
         * Si Storage falla, persistimos el trabajo
         * en la cola para reintentar posteriormente.
         */
        $mediaCleanupPending = false;

        try {
            $this->imageService->delete(
                filename: $filename,
                directory: NewsImage::MEDIA_DIRECTORY,
            );
        } catch (Throwable $exception) {
            $mediaCleanupPending = true;

            Log::error(
                'No se pudieron eliminar inmediatamente los archivos físicos de una imagen de noticia.',
                [
                    'news_id' => $news->id,
                    'filename' => $filename,
                    'exception' => $exception->getMessage(),
                ]
            );

            CleanupImageFiles::dispatch(
                filename: $filename,
                directory: NewsImage::MEDIA_DIRECTORY,
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente.',
            'media_cleanup_pending' => $mediaCleanupPending,
        ]);
    }

    public function reorder(
        ReorderNewsMediaRequest $request,
        News $news
    ): AnonymousResourceCollection {
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

            /*
               * Comprobamos el conjunto real después
               * de obtener el lock.
               */
            $currentIds = $news
                ->images()
                ->pluck('id')
                ->sort()
                ->values();

            abort_unless(
                $currentIds->all() === $requestIds->all(),
                422,
                'Debe enviar todas las imágenes de la noticia.'
            );

            /*
               * Posiciones temporales para evitar
               * UNIQUE(news_id, position).
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
            ->each(function (NewsImage $image, int $position) {
                $image->update([
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
