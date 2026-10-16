<?php

namespace App\Services\Content;

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Jobs\Media\CleanupImageFiles;
use App\Models\Content\SiteMedia;
use App\Services\Media\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SiteMediaService
{
    public function __construct(private readonly ImageService $images) {}

    public function store(UploadedFile $image, string $profile, int $userId): SiteMedia
    {
        $directory = 'content/heroes/'.$profile;
        $dimensions = config('content.images.'.$profile);
        $filename = $this->images->store($image, new ImageOptions(
            directory: $directory,
            formats: [ImageFormat::WEBP],
            resizeMode: ImageResizeMode::COVER_DOWN,
            width: $dimensions['width'],
            height: $dimensions['height'],
            webpQuality: 82,
        ));
        $disk = config('media.disk', 'public');
        $path = $directory.'/'.$filename.'.webp';
        try {
            $media = new SiteMedia([
                'profile' => $profile, 'disk' => $disk, 'path' => $path,
                'original_name' => Str::limit($image->getClientOriginalName(), 255, ''),
                'mime_type' => 'image/webp', 'size_bytes' => Storage::disk($disk)->size($path),
            ]);
            $media->uploaded_by = $userId;
            $media->save();

            return $media;
        } catch (Throwable $exception) {
            try {
                $this->images->delete($filename, $directory);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
                try {
                    CleanupImageFiles::dispatch($filename, $directory);
                } catch (Throwable $dispatchException) {
                    report($dispatchException);
                }
            }
            throw $exception;
        }
    }

    public function delete(SiteMedia $media): void
    {
        DB::transaction(function () use ($media): void {
            $media = SiteMedia::query()->lockForUpdate()->findOrFail($media->id);
            abort_if($media->desktopSections()->exists() || $media->mobileSections()->exists(), 409, 'La imagen está en uso. Reemplácela o desvincúlela antes de eliminarla.');
            if (! Storage::disk($media->disk)->delete($media->path)) {
                throw new RuntimeException('No se pudo eliminar el archivo de imagen.');
            }
            $media->delete();
        });
    }
}
