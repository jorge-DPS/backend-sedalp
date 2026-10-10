<?php

namespace App\Services\Territory;

use App\Models\Territory\Region;
use App\Models\Territory\RegionBoundary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegionBoundaryService
{
    public function find(Region $region): RegionBoundary
    {
        return $region->boundary()
            ->select(['id', 'region_id', 'updated_at'])
            ->selectRaw('ST_AsGeoJSON(ST_ForcePolygonCCW(geom), 9, 0) AS geometry')
            ->firstOrFail()
            ->setRelation('region', $region);
    }

    /** @param array{type?: mixed, features?: mixed} $collection */
    public function import(Region $region, array $collection, string $sourceFilename, int $sourceSrid, bool $replace = false): RegionBoundary
    {
        $features = $collection['features'] ?? null;

        if (($collection['type'] ?? null) !== 'FeatureCollection' || ! is_array($features) || $features === [] || count($features) > 10000) {
            throw ValidationException::withMessages(['file' => 'Se requiere un FeatureCollection con entre 1 y 10000 polígonos.']);
        }

        return DB::transaction(function () use ($region, $features, $sourceFilename, $sourceSrid, $replace): RegionBoundary {
            $region = Region::query()->lockForUpdate()->findOrFail($region->id);
            $boundary = $region->boundary()->first();

            if ($boundary !== null && ! $replace) {
                throw ValidationException::withMessages(['file' => 'La región ya tiene un límite. Utilice --replace para reemplazarlo.']);
            }

            $geometries = [];
            $properties = [];

            foreach ($features as $feature) {
                $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;

                if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                    throw ValidationException::withMessages(['file' => 'Solo se admiten geometrías Polygon y MultiPolygon.']);
                }

                $geometries[] = $geometry;
                $properties[] = $feature['properties'] ?? [];
            }

            $geometryJson = json_encode(['type' => 'GeometryCollection', 'geometries' => $geometries], JSON_THROW_ON_ERROR);
            $result = DB::selectOne(
                'SELECT ST_IsValid(geom) AND NOT ST_IsEmpty(geom) AND ST_XMin(Box3D(geom)) >= -180 AND ST_XMax(Box3D(geom)) <= 180 AND ST_YMin(Box3D(geom)) >= -90 AND ST_YMax(Box3D(geom)) <= 90 AS valid FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom) AS imported',
                [$geometryJson],
            );

            if (! $result->valid) {
                throw ValidationException::withMessages(['file' => 'La geometría está vacía o es inválida. Corrija la capa de origen antes de importar.']);
            }

            $result = DB::selectOne(
                'SELECT ST_AsEWKT(ST_Multi(ST_UnaryUnion(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))) AS geometry',
                [$geometryJson],
            );

            $boundary ??= new RegionBoundary;
            $boundary->fill([
                'region_id' => $region->id,
                'source_filename' => basename($sourceFilename),
                'source_srid' => $sourceSrid,
                'source_properties' => $properties,
            ]);
            $boundary->geom = $result->geometry;
            $boundary->save();

            return $boundary;
        });
    }
}
