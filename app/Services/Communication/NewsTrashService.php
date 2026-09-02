<?php

namespace App\Services\Communication;

use App\Jobs\Media\CleanupImageFiles;
use App\Models\Communication\News;
use App\Models\Communication\NewsImage;
use App\Services\Media\ImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NewsTrashService
{
    private const RELATIONS = [
        'images',
        'videos',
        'creator:id,email',
        'updater:id,email',
    ];

    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function restore(
        News $news,
        int $userId
    ): News {
        return DB::transaction(function () use (
            $news,
            $userId
        ) {
            $news->restore();

            $news->updated_by = $userId;
            $news->save();

            return $news->load(self::RELATIONS);
        });
    }

    /**
     * @return array<string>
     */
    public function forceDelete(
        News $news,
        int $userId
    ): array {
        $newsId = $news->id;

        $filenames = $news->images
            ->pluck('filename')
            ->all();

        DB::transaction(function () use ($news) {
            $news->forceDelete();
        });

        /*
         * PostgreSQL ya eliminó definitivamente
         * la noticia y sus registros relacionados.
         *
         * Ahora limpiamos los archivos físicos.
         */
        $failedFiles = [];

        foreach ($filenames as $filename) {
            try {
                $this->imageService->delete(
                    filename: $filename,
                    directory: NewsImage::MEDIA_DIRECTORY,
                );
            } catch (Throwable $exception) {
                $failedFiles[] = $filename;

                Log::error(
                    'No se pudieron eliminar archivos físicos de una noticia eliminada definitivamente.',
                    [
                        'news_id' => $newsId,
                        'filename' => $filename,
                        'user_id' => $userId,
                        'exception' => $exception->getMessage(),
                    ]
                );

                CleanupImageFiles::dispatch(
                    filename: $filename,
                    directory: NewsImage::MEDIA_DIRECTORY,
                );
            }
        }

        Log::warning(
            'Noticia eliminada definitivamente.',
            [
                'news_id' => $newsId,
                'user_id' => $userId,
                'media_cleanup_pending' => $failedFiles !== [],
            ]
        );

        return $failedFiles;
    }
}
