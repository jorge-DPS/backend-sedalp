<?php

namespace App\Jobs\Municipal;

use App\Services\Municipal\MunicipalPoaUploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CompleteMunicipalPoaUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public readonly string $uploadId)
    {
        $this->timeout = (int) config('municipal.poa_assembly_timeout');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MunicipalPoaUploadService $uploads): void
    {
        $uploads->process($this->uploadId);
    }

    public function failed(?Throwable $exception): void
    {
        app(MunicipalPoaUploadService::class)->markFailed($this->uploadId);
    }
}
