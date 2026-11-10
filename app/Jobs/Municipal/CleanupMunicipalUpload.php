<?php

namespace App\Jobs\Municipal;

use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalPoaDocument;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class CleanupMunicipalUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly string $path) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        if (preg_match('~^municipal/(?:poas/[0-9]+/[a-f0-9-]{36}\\.pdf|authorities/[0-9]+/[a-f0-9-]{36}\\.webp)$~D', $this->path) !== 1) {
            throw new InvalidArgumentException('La limpieza solo admite archivos municipales generados por el servidor.');
        }

        if (MunicipalPoaDocument::where('pdf_path', $this->path)->exists()
            || MunicipalAuthority::where('photo_path', $this->path)->exists()) {
            return;
        }

        if (! Storage::disk('local')->delete($this->path)) {
            throw new RuntimeException('No se pudo limpiar la carga municipal fallida.');
        }
    }
}
