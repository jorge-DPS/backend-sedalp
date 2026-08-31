<?php

namespace App\DTOs\Media;

use App\Enums\Media\ImageFormat;
use App\Enums\Media\ImageResizeMode;
use InvalidArgumentException;

final readonly class ImageOptions
{
    public string $directory;

    /**
     * @var list<ImageFormat>
     */
    public array $formats;

    /**
     * @param  list<ImageFormat>  $formats
     */
    public function __construct(
        string $directory,

        array $formats = [
            ImageFormat::WEBP,
            ImageFormat::PNG,
            ImageFormat::JPEG,
        ],

        public ImageResizeMode $resizeMode = ImageResizeMode::SCALE_DOWN,

        public ?int $width = null,

        public ?int $height = null,

        public int $webpQuality = 80,

        public int $jpegQuality = 82,

        public bool $jpegProgressive = true,
    ) {
        $this->directory = $this->normalizeDirectory(
            $directory
        );

        $this->validateFormats($formats);

        $this->formats = $formats;

        $this->validateDimensions();

        $this->validateQuality(
            $this->webpQuality,
            'webpQuality'
        );

        $this->validateQuality(
            $this->jpegQuality,
            'jpegQuality'
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

        $directory = trim($directory, '/');

        if ($directory === '') {
            throw new InvalidArgumentException(
                'El directorio de imágenes es obligatorio.'
            );
        }

        /*
         * Protección adicional aunque el directorio
         * solamente sea definido desde backend.
         */
        if (
            str_contains($directory, '..')
            || ! preg_match(
                '#^[a-zA-Z0-9/_-]+$#',
                $directory
            )
        ) {
            throw new InvalidArgumentException(
                'El directorio de imágenes no es válido.'
            );
        }

        return $directory;
    }

    private function validateFormats(array $formats): void
    {
        if ($formats === []) {
            throw new InvalidArgumentException(
                'Debe especificarse al menos un formato.'
            );
        }

        foreach ($formats as $format) {
            if (! $format instanceof ImageFormat) {
                throw new InvalidArgumentException(
                    'Formato de imagen no válido.'
                );
            }
        }

        $values = array_map(
            fn (ImageFormat $format) => $format->value,
            $formats
        );

        if (
            count($values)
            !== count(array_unique($values))
        ) {
            throw new InvalidArgumentException(
                'No deben existir formatos repetidos.'
            );
        }
    }

    private function validateDimensions(): void
    {
        if (
            $this->width !== null
            && $this->width <= 0
        ) {
            throw new InvalidArgumentException(
                'El ancho debe ser mayor a cero.'
            );
        }

        if (
            $this->height !== null
            && $this->height <= 0
        ) {
            throw new InvalidArgumentException(
                'El alto debe ser mayor a cero.'
            );
        }

        if (
            $this->resizeMode === ImageResizeMode::SCALE_DOWN
            && $this->width === null
            && $this->height === null
        ) {
            throw new InvalidArgumentException(
                'SCALE_DOWN necesita width o height.'
            );
        }

        if (
            $this->resizeMode === ImageResizeMode::COVER_DOWN
            && (
                $this->width === null
                || $this->height === null
            )
        ) {
            throw new InvalidArgumentException(
                'COVER_DOWN necesita width y height.'
            );
        }
    }

    private function validateQuality(
        int $quality,
        string $field
    ): void {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException(
                "{$field} debe estar entre 1 y 100."
            );
        }
    }
}
