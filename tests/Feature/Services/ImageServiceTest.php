<?php

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Jobs\Media\CleanupImageFiles;
use App\Services\Media\ImageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\EncodedImage;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;

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

it('rechaza nombres de archivo que no sean uuid', function () {
    Storage::shouldReceive('disk')->never();

    $service = new ImageService(
        Mockery::mock(ImageManagerInterface::class)
    );

    expect(
        fn () => $service->delete(
            filename: '../../archivo',
            directory: 'communication/news',
        )
    )->toThrow(
        RuntimeException::class,
        'Nombre de archivo de imagen no válido.'
    );
});

it('encola la limpieza cuando falla una escritura parcial', function () {
    Queue::fake();

    $file = UploadedFile::fake()->image(
        'parcial.jpg',
        100,
        100
    );

    $image = Mockery::mock(
        ImageInterface::class
    );

    $image
        ->shouldReceive('encodeUsingFormat')
        ->twice()
        ->andReturn(
            new EncodedImage('contenido')
        );

    $imageManager = Mockery::mock(
        ImageManagerInterface::class
    );

    $imageManager
        ->shouldReceive('decode')
        ->once()
        ->with($file)
        ->andReturn($image);

    $disk = Mockery::mock(
        FilesystemAdapter::class
    );

    $disk
        ->shouldReceive('put')
        ->twice()
        ->andReturn(true, false);

    $disk
        ->shouldReceive('delete')
        ->once()
        ->andReturn(false);

    Storage::shouldReceive('disk')
        ->times(3)
        ->with(
            config('media.disk', 'public')
        )
        ->andReturn($disk);

    $service = new ImageService(
        $imageManager
    );

    $options = new ImageOptions(
        directory: 'communication/news',
        formats: [
            ImageFormat::WEBP,
            ImageFormat::PNG,
        ],
        resizeMode: ImageResizeMode::NONE,
    );

    expect(
        fn () => $service->store(
            file: $file,
            options: $options,
        )
    )->toThrow(
        RuntimeException::class,
        'No se pudo almacenar'
    );

    Queue::assertPushed(
        CleanupImageFiles::class,
        fn (CleanupImageFiles $job): bool => $job->directory === 'communication/news'
    );
});
