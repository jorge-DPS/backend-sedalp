<?php

use App\Jobs\Media\CleanupImageFiles;
use App\Models\Communication\NewsImage;
use App\Services\Media\ImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;

it('ejecuta la limpieza física mediante ImageService', function () {
    $imageService = Mockery::mock(
        ImageService::class
    );

    $imageService
        ->shouldReceive('delete')
        ->once()
        ->with(
            'dddddddd-dddd-dddd-dddd-dddddddddddd',
            NewsImage::MEDIA_DIRECTORY,
        );

    $job = new CleanupImageFiles(
        filename: 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        directory: NewsImage::MEDIA_DIRECTORY,
    );

    $job->handle($imageService);
});

it('configura reintentos unicidad y timeout para la limpieza', function () {
    $job = new CleanupImageFiles(
        filename: 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        directory: NewsImage::MEDIA_DIRECTORY,
    );

    expect($job)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->tries)->toBe(5)
        ->and($job->timeout)->toBe(30)
        ->and($job->uniqueFor)->toBe(3600)
        ->and($job->uniqueId())->toBe(
            config('media.disk', 'public')
            .':communication/news/'
            .'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'
        )
        ->and($job->backoff())->toBe([
            60,
            300,
            900,
            1800,
        ]);
});
