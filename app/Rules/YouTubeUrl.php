<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeUrl implements ValidationRule
{
    private const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtu.be',
        'www.youtu.be',
    ];

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (! is_string($value)) {
            $fail('El campo :attribute debe contener una URL válida de YouTube.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host)) {
            $fail('El campo :attribute debe contener una URL válida de YouTube.');

            return;
        }

        $host = strtolower(rtrim($host, '.'));

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            $fail('El campo :attribute debe contener una URL de YouTube.');
        }
    }
}
