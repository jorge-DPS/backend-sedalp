<?php

namespace App\Services\Territory;

use App\Models\Territory\MapLayer;
use App\Models\Territory\Region;
use App\Models\Territory\RegionBoundary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class LegacyMapLayerService
{
    public function import(int $regionId, int $userId): bool
    {
        $path = null;
        try {
            return DB::transaction(function () use ($regionId, $userId, &$path): bool {
                $region = Region::query()->lockForUpdate()->find($regionId);
                if ($region === null || MapLayer::query()->where('kind', 'boundary')->where('region_id', $regionId)->exists()) {
                    return false;
                }
                $boundary = RegionBoundary::query()->where('region_id', $regionId)->lockForUpdate()
                    ->select('*')->selectRaw('ST_AsGeoJSON(geom, 15, 0) AS geometry, ST_NPoints(geom) AS position_count')->first();
                if ($boundary === null) {
                    return false;
                }
                $snapshot = json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [['type' => 'Feature', 'properties' => (object) $boundary->source_properties, 'geometry' => $boundary->geometry]],
                ], JSON_THROW_ON_ERROR);
                $path = 'maps/originals/'.Str::uuid().'.geojson';
                abort_unless(Storage::disk('local')->put($path, $snapshot), 503, 'No se pudo guardar la copia del límite.');
                $layer = new MapLayer([
                    'name' => $region->name, 'region_id' => $regionId, 'kind' => 'boundary', 'category' => 'boundaries',
                    'description' => 'Límite incorporado desde el registro regional existente.',
                ]);
                $layer->created_by = $userId;
                $layer->save();
                $version = $layer->versions()->create([
                    'number' => 1, 'uploaded_by' => $userId,
                    'source_filename' => 'region-'.$regionId.'-legacy.geojson',
                    'source_path' => $path, 'sha256' => hash('sha256', $snapshot),
                    'summary' => ['feature_count' => 1, 'geometry_types' => ['MultiPolygon' => 1],
                        'position_count' => (int) $boundary->position_count, 'srid' => 4326],
                ]);
                DB::insert('INSERT INTO map_features (map_layer_version_id, position, properties, geom)
                    SELECT ?, 1, source_properties, geom FROM region_boundaries WHERE id = ?', [$version->id, $boundary->id]);

                return true;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
