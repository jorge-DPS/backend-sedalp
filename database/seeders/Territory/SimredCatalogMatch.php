<?php

namespace Database\Seeders\Territory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/** Resuelve equivalencias de mayúsculas sin perder los nombres literales. */
final class SimredCatalogMatch
{
    public static function normalize(string $name): string
    {
        return Str::lower(trim($name));
    }

    /**
     * @param Collection<int, Model> $records
     * @param list<string> $names
     */
    public static function one(Collection $records, array $names, ?string $code = null): ?Model
    {
        $normalized = array_map(self::normalize(...), $names);
        $matches = $records->filter(fn (Model $record): bool =>
            in_array(self::normalize($record->name), $normalized, true)
            || ($code !== null && $record->getAttribute('code') === $code)
        );

        if ($matches->count() > 1) {
            throw new RuntimeException('Hay registros duplicados o ambiguos para: '.implode(' / ', $names));
        }

        $record = $matches->first();

        if ($record?->trashed()) {
            throw new RuntimeException('El registro está eliminado lógicamente y requiere revisión: '.implode(' / ', $names));
        }

        return $record;
    }
}
