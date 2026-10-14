<?php

namespace App\Services\Territory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class GeoJsonService
{
    /** @return array{type: string, features: array<int, array<string, mixed>>, summary: array<string, mixed>} */
    public function validate(string $json, bool $repairGeometries = false): array
    {
        if (strlen($json) > config('maps.max_geojson_bytes')) {
            $this->invalid('La capa convertida supera el tamaño permitido.');
        }

        try {
            $document = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->invalid('El archivo no contiene JSON válido.');
        }

        if (! is_array($document)) {
            $this->invalid('Se requiere un GeoJSON Feature o FeatureCollection.');
        }
        if (isset($document['crs']) && ! in_array($document['crs']['properties']['name'] ?? '', ['EPSG:4326', 'urn:ogc:def:crs:OGC:1.3:CRS84', 'urn:ogc:def:crs:EPSG::4326'], true)) {
            $this->invalid('El GeoJSON debe estar en WGS84 (EPSG:4326). Exporte el archivo en ese sistema.');
        }

        $features = match ($document['type'] ?? '') {
            'Feature' => [$document],
            'FeatureCollection' => $document['features'] ?? [],
            default => [],
        };
        if (! is_array($features) || ! array_is_list($features) || count($features) < 1 || count($features) > config('maps.max_features')) {
            $this->invalid('La capa debe contener entre 1 y '.config('maps.max_features').' elementos.');
        }

        $positions = 0;
        $types = [];
        $normalized = [];
        foreach ($features as $index => $feature) {
            $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;
            if (($feature['type'] ?? '') !== 'Feature' || ! is_array($geometry) || ! in_array($geometry['type'] ?? '', ['Point', 'MultiPoint', 'LineString', 'MultiLineString', 'Polygon', 'MultiPolygon'], true)) {
                $this->invalid('El elemento '.($index + 1).' debe ser un punto, línea o polígono.');
            }
            if (isset($geometry['crs'])) {
                $this->invalid('Declare todas las coordenadas en WGS84 sin sistemas particulares por elemento.');
            }
            $this->positions($geometry['coordinates'] ?? null, $positions);
            $properties = $feature['properties'] ?? [];
            if (! is_array($properties) || ($properties !== [] && array_is_list($properties))) {
                $this->invalid('Los atributos de cada elemento deben ser un objeto JSON.');
            }
            $normalized[] = ['type' => 'Feature', 'properties' => (object) $properties, 'geometry' => $geometry];

        }

        try {
            $rows = DB::transaction(fn (): array => DB::select(
                "SELECT ordinality, repaired, ST_IsValid(geom, 0) AND NOT ST_IsEmpty(geom) AS valid,
                 ST_AsGeoJSON(ST_ForcePolygonCCW(ST_Force2D(geom)), 15, 0) AS geometry
                 FROM (SELECT ordinality, NOT ST_IsValid(original, 0) AS repaired, CASE WHEN ?::boolean AND NOT ST_IsValid(original, 0) THEN ST_CollectionExtract(ST_MakeValid(original), ST_Dimension(original) + 1) ELSE original END AS geom FROM (SELECT ordinality, ST_SetSRID(ST_GeomFromGeoJSON((item->'geometry')::text),4326) AS original
                 FROM jsonb_array_elements(?::jsonb) WITH ORDINALITY AS items(item, ordinality)) AS parsed) AS normalized ORDER BY ordinality",
                [$repairGeometries ? 'true' : 'false', json_encode($normalized, JSON_THROW_ON_ERROR)],
            ));
        } catch (QueryException) {
            $this->invalid('Una geometría está mal formada. Revise los anillos y las coordenadas del archivo.');
        }

        $types = [];
        $repairedCount = 0;
        $positions = 0;
        foreach ($rows as $index => $row) {
            if (! $row->valid) {
                $this->invalid('La geometría del elemento '.($index + 1).' es inválida o está vacía.');
            }
            $normalized[$index]['geometry'] = json_decode($row->geometry, true, 128, JSON_THROW_ON_ERROR);
            $type = $normalized[$index]['geometry']['type'];
            $this->positions($normalized[$index]['geometry']['coordinates'], $positions);
            $types[$type] = ($types[$type] ?? 0) + 1;
            $repairedCount += (int) $row->repaired;
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $normalized,
            'summary' => ['feature_count' => count($normalized), 'geometry_types' => $types, 'position_count' => $positions, 'srid' => 4326, 'repaired_features' => $repairedCount],
        ];
    }

    private function positions(mixed $coordinates, int &$count, int $depth = 0): void
    {
        if (! is_array($coordinates) || ! array_is_list($coordinates) || $coordinates === [] || $depth > 4) {
            $this->invalid('Se encontraron coordenadas vacías o mal formadas.');
        }
        if (is_numeric($coordinates[0]) && ! is_array($coordinates[0])) {
            if (count($coordinates) < 2 || count($coordinates) > 3) {
                $this->invalid('Cada coordenada debe tener longitud y latitud.');
            }
            foreach ($coordinates as $value) {
                if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                    $this->invalid('Las coordenadas deben ser números finitos.');
                }
            }
            if (abs($coordinates[0]) > 180 || abs($coordinates[1]) > 90) {
                $this->invalid('Las coordenadas están fuera de WGS84. Revise la proyección del archivo.');
            }
            if (++$count > config('maps.max_positions')) {
                $this->invalid('La capa supera el límite de coordenadas permitido.');
            }

            return;
        }
        foreach ($coordinates as $child) {
            $this->positions($child, $count, $depth + 1);
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
