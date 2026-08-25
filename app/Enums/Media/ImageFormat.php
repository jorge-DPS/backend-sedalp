<?php

namespace App\Enums\Media;

use Intervention\Image\Format as InterventionFormat;

enum ImageFormat: string
{
    case WEBP = 'webp';

    case PNG = 'png';

    case JPEG = 'jpeg';

    public function interventionFormat(): InterventionFormat
    {
        return match ($this) {
            self::WEBP => InterventionFormat::WEBP,
            self::PNG => InterventionFormat::PNG,
            self::JPEG => InterventionFormat::JPEG,
        };
    }
}
