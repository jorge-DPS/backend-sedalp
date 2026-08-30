<?php

use App\Services\Media\ImageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Mockery\MockInterface;
use RuntimeException;

it('detecta cuando falla la eliminación física de una imagen', function () {
    $disk = Mockery::mock(
        FilesystemAdapter::class
    );

    $disk
        ->shouldReceive('delete')
        ->once()
        ->with([
            'communication/news/11111111-1111-1111-1111-111111111111.webp',
            'communication/news/11111111-1111-1111-1111-111111111111.png',
            'communication/news/11111111-1111-1111-1111-111111111111.jpeg',
        ])
        ->andReturn(false);

    Storage::shouldReceive('disk')
        ->once()
        ->with(
            config('media.disk', 'public')
        )
        ->andReturn($disk);

    $imageManager = Mockery::mock(
        ImageManagerInterface::class
    );

    $service = new ImageService(
        $imageManager
    );

    expect(
        fn () => $service->delete(
            filename: '11111111-1111-1111-1111-111111111111',
            directory: 'communication/news',
        )
    )->toThrow(
        RuntimeException::class,
        'No se pudieron eliminar todas las variantes'
    );
});

it('elimina correctamente todas las variantes de una imagen', function () {
    $disk = Mockery::mock(
        FilesystemAdapter::class
    );

    $disk
        ->shouldReceive('delete')
        ->once()
        ->with([
            'communication/news/22222222-2222-2222-2222-222222222222.webp',
            'communication/news/22222222-2222-2222-2222-222222222222.png',
            'communication/news/22222222-2222-2222-2222-222222222222.jpeg',
        ])
        ->andReturn(true);

    Storage::shouldReceive('disk')
        ->once()
        ->with(
            config('media.disk', 'public')
        )
        ->andReturn($disk);

    $imageManager = Mockery::mock(
        ImageManagerInterface::class
    );

    $service = new ImageService(
        $imageManager
    );

    $service->delete(
        filename: '22222222-2222-2222-2222-222222222222',
        directory: 'communication/news',
    );

    expect(true)->toBeTrue();
});
