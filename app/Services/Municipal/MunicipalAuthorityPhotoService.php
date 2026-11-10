<?php

namespace App\Services\Municipal;

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Jobs\Municipal\CleanupMunicipalUpload;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Territory\Municipality;
use App\Services\Media\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MunicipalAuthorityPhotoService
{
    public function __construct(private readonly ImageService $images) {}

    public function store(MunicipalAuthority $authority, UploadedFile $photo): MunicipalAuthority
    {
        $directory = 'municipal/authorities/'.$authority->id;
        $filename = $this->images->store($photo, new ImageOptions(
            directory: $directory,
            formats: [ImageFormat::WEBP],
            resizeMode: ImageResizeMode::COVER_DOWN,
            width: (int) config('municipal.photo_width'),
            height: (int) config('municipal.photo_height'),
            disk: 'local',
            visibility: 'private',
        ));
        $path = $directory.'/'.$filename.'.webp';

        try {
            return DB::transaction(function () use ($authority, $path): MunicipalAuthority {
                if (Municipality::query()->where('active', true)->sharedLock()->find($authority->municipality_id) === null) {
                    throw ValidationException::withMessages(['municipality_id' => 'El municipio no está disponible.']);
                }
                $authority = MunicipalAuthority::query()->lockForUpdate()->findOrFail($authority->id);
                if (! $authority->update(['photo_path' => $path])) {
                    throw new RuntimeException('No se pudo registrar la fotografía.');
                }

                return $authority->refresh();
            }, 3);
        } catch (Throwable $exception) {
            try {
                (new CleanupMunicipalUpload($path))->handle();
            } catch (Throwable $cleanupException) {
                report($cleanupException);
                try {
                    CleanupMunicipalUpload::dispatch($path);
                } catch (Throwable $dispatchException) {
                    report($dispatchException);
                }
            }
            throw $exception;
        }
    }

    public function readablePath(MunicipalAuthority $authority): string
    {
        $path = $authority->photo_path;
        abort_unless(is_string($path)
            && preg_match('~^municipal/authorities/'.$authority->id.'/[a-f0-9-]{36}\\.webp$~D', $path) === 1, 404);
        abort_unless(Storage::disk('local')->exists($path) && Storage::disk('local')->mimeType($path) === 'image/webp', 404);

        return $path;
    }
}
