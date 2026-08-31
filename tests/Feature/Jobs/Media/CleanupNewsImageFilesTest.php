<?php

use App\Jobs\Media\CleanupNewsImageFiles;
use App\Models\Communication\NewsImage;
use App\Services\Media\ImageService;

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

    $job = new CleanupNewsImageFiles(
        filename: 'dddddddd-dddd-dddd-dddd-dddddddddddd',
        directory: NewsImage::MEDIA_DIRECTORY,
    );

    $job->handle($imageService);
});

it('configura reintentos y backoff para la limpieza', function () {
    $job = new CleanupNewsImageFiles(
        filename: 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
        directory: NewsImage::MEDIA_DIRECTORY,
    );

    expect($job->tries)
        ->toBe(5);

    expect($job->backoff())
        ->toBe([
            60,
            300,
            900,
            1800,
        ]);
});
