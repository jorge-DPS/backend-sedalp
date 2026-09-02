<?php

namespace App\Jobs\Media;

use App\Services\Media\ImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupImageFiles implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $filename,
        public readonly string $directory,
    ) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [
            60,
            300,
            900,
            1800,
        ];
    }

    public function uniqueId(): string
    {
        return sprintf(
            '%s:%s/%s',
            config('media.disk', 'public'),
            $this->directory,
            $this->filename,
        );
    }

    public function handle(
        ImageService $imageService
    ): void {
        $imageService->delete(
            filename: $this->filename,
            directory: $this->directory,
        );
    }

    public function failed(
        ?Throwable $exception
    ): void {
        Log::critical(
            'Falló definitivamente la limpieza de archivos físicos de una imagen.',
            [
                'filename' => $this->filename,
                'directory' => $this->directory,
                'exception' => $exception?->getMessage(),
            ]
        );
    }
}
