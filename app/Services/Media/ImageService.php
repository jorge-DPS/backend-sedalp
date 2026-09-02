<?php

namespace App\Services\Media;

use App\DTOs\Media\ImageOptions;
use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use App\Jobs\Media\CleanupImageFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;
use Throwable;

class ImageService
{
    public function __construct(
        private readonly ImageManagerInterface $imageManager
    ) {}

    /**
     * Procesa y almacena una imagen.
     *
     * Retorna únicamente el nombre base:
     *
     * 550e8400-e29b-41d4-a716-446655440000
     *
     * SIN extensión.
     */
    public function store(
        UploadedFile $file,
        ImageOptions $options
    ): string {
        $filename = (string) Str::uuid();

        try {
            $image = $this->imageManager
                ->decode($file);

            $image = $this->resize(
                image: $image,
                options: $options,
            );

            foreach ($options->formats as $format) {

                $contents = $this->encode(
                    image: $image,
                    format: $format,
                    options: $options,
                );

                $path = $this->path(
                    directory: $options->directory,
                    filename: $filename,
                    format: $format,
                );

                $stored = Storage::disk(
                    $this->disk()
                )->put(
                    $path,
                    $contents,
                    'public'
                );

                if (! $stored) {
                    throw new RuntimeException(
                        "No se pudo almacenar {$path}."
                    );
                }
            }

            return $filename;
        } catch (Throwable $exception) {

            /*
     * Si falló el procesamiento o almacenamiento,
     * intentamos limpiar cualquier variante parcial.
     *
     * Si la limpieza también falla, no debemos
     * ocultar la excepción original.
     */
            try {
                $this->delete(
                    filename: $filename,
                    directory: $options->directory,
                );
            } catch (Throwable $cleanupException) {
                report($cleanupException);

                try {
                    CleanupImageFiles::dispatch(
                        filename: $filename,
                        directory: $options->directory,
                    );
                } catch (Throwable $dispatchException) {
                    report($dispatchException);
                }
            }

            throw $exception;
        }
    }

    /**
     * Elimina todas las posibles variantes.
     *
     * No importa si algún formato no existe.
     */
    public function delete(
        string $filename,
        string $directory
    ): void {
        $directory = $this->normalizeDirectory(
            $directory
        );

        if (! Str::isUuid($filename)) {
            throw new RuntimeException(
                'Nombre de archivo de imagen no válido.'
            );
        }

        $paths = array_map(
            fn (ImageFormat $format) => $this->path(
                directory: $directory,
                filename: $filename,
                format: $format,
            ),
            ImageFormat::cases()
        );

        $deleted = Storage::disk(
            $this->disk()
        )->delete($paths);

        if (! $deleted) {
            throw new RuntimeException(
                sprintf(
                    'No se pudieron eliminar todas las variantes de la imagen [%s] del directorio [%s].',
                    $filename,
                    $directory,
                )
            );
        }
    }

    /**
     * Devuelve la URL pública base del directorio.
     *
     * Ejemplo local:
     *
     * /storage/communication/news
     *
     * Ejemplo S3:
     *
     * https://bucket.../communication/news
     */
    public function publicBaseUrl(
        string $directory
    ): string {
        $directory = $this->normalizeDirectory(
            $directory
        );

        return rtrim(
            Storage::disk(
                $this->disk()
            )->url($directory),
            '/'
        );
    }

    private function resize(
        ImageInterface $image,
        ImageOptions $options
    ): ImageInterface {
        return match ($options->resizeMode) {

            ImageResizeMode::NONE => $image,

            ImageResizeMode::SCALE_DOWN => $image->scaleDown(
                width: $options->width,
                height: $options->height,
            ),

            ImageResizeMode::COVER_DOWN => $image->coverDown(
                width: $options->width,
                height: $options->height,
            ),
        };
    }

    private function encode(
        ImageInterface $image,
        ImageFormat $format,
        ImageOptions $options
    ): string {
        $encoded = match ($format) {

            ImageFormat::WEBP => $image->encodeUsingFormat(
                $format->interventionFormat(),
                quality: $options->webpQuality,
            ),

            ImageFormat::PNG => $image->encodeUsingFormat(
                $format->interventionFormat(),
            ),

            ImageFormat::JPEG => $image->encodeUsingFormat(
                $format->interventionFormat(),
                progressive: $options->jpegProgressive,
                quality: $options->jpegQuality,
            ),
        };

        return (string) $encoded;
    }

    private function path(
        string $directory,
        string $filename,
        ImageFormat $format
    ): string {
        return sprintf(
            '%s/%s.%s',
            $directory,
            $filename,
            $format->value,
        );
    }

    private function disk(): string
    {
        return config(
            'media.disk',
            'public'
        );
    }

    private function normalizeDirectory(
        string $directory
    ): string {
        $directory = str_replace(
            '\\',
            '/',
            trim($directory)
        );

        $directory = trim(
            $directory,
            '/'
        );

        if (
            $directory === ''
            || str_contains($directory, '..')
            || ! preg_match(
                '#^[a-zA-Z0-9/_-]+$#',
                $directory
            )
        ) {
            throw new RuntimeException(
                'Directorio de imágenes no válido.'
            );
        }

        return $directory;
    }
}
