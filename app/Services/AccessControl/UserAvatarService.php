<?php

namespace App\Services\AccessControl;

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Jobs\Media\CleanupImageFiles;
use App\Models\User;
use App\Services\Media\ImageService;
use Illuminate\Http\UploadedFile;
use Throwable;

class UserAvatarService
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function store(UploadedFile $file): string
    {
        return $this->imageService->store(
            file: $file,
            options: new ImageOptions(
                directory: User::AVATAR_DIRECTORY,
                formats: [ImageFormat::WEBP],
                resizeMode: ImageResizeMode::COVER_DOWN,
                width: 512,
                height: 512,
            ),
        );
    }

    public function delete(?string $filename): void
    {
        if ($filename === null) {
            return;
        }

        try {
            $this->imageService->delete(
                filename: $filename,
                directory: User::AVATAR_DIRECTORY,
            );
        } catch (Throwable $exception) {
            report($exception);

            try {
                CleanupImageFiles::dispatch(
                    filename: $filename,
                    directory: User::AVATAR_DIRECTORY,
                )->afterCommit();
            } catch (Throwable $dispatchException) {
                report($dispatchException);
            }
        }
    }
}
