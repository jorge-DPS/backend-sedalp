<?php

namespace App\Services\Territory;

use App\Models\Territory\MapFeature;
use App\Models\Territory\MapLayer;
use App\Models\Territory\MapLayerVersion;
use App\Models\Territory\Region;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MapLayerService
{
    public function __construct(
        private readonly MapFileService $files,
        private readonly RegionBoundaryService $boundaries,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function save(UploadedFile $file, array $attributes, int $userId, ?MapLayer $existing = null): MapLayer
    {
        $collection = $this->files->read($file, (bool) ($attributes['repair_geometries'] ?? false));
        $path = $file->storeAs('maps/originals', (string) Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        abort_unless(is_string($path), 503, 'No se pudo guardar el archivo original.');

        try {
            return DB::transaction(function () use ($file, $attributes, $userId, $existing, $collection, $path): MapLayer {
                $layer = $existing === null
                    ? new MapLayer
                    : MapLayer::query()->lockForUpdate()->findOrFail($existing->id);

                if ($existing !== null) {
                    abort_unless((int) $layer->versions()->max('id') === (int) $attributes['expected_version_id'], 409, 'La capa cambió. Vuelva a abrirla antes de actualizar.');
                } else {
                    $layer->fill(collect($attributes)->only(['name', 'description', 'region_id', 'kind'])->all());
                    $layer->created_by = $userId;
                }

                if ($layer->region_id !== null) {
                    $region = Region::query()->lockForUpdate()->findOrFail($layer->region_id);
                    if ($layer->kind === 'boundary') {
                        if ($existing === null && MapLayer::query()->where('region_id', $region->id)->where('kind', 'boundary')->exists()) {
                            throw ValidationException::withMessages(['region_id' => 'Esta región ya tiene una capa de límite. Actualice su versión.']);
                        }
                        if ($region->boundary()->exists() && ! ($attributes['replace_boundary'] ?? false)) {
                            throw ValidationException::withMessages(['replace_boundary' => 'Confirme que desea actualizar el límite regional existente.']);
                        }
                        $this->boundaries->import($region, $collection, $file->getClientOriginalName(), 4326, (bool) ($attributes['replace_boundary'] ?? false));
                    }
                }

                $layer->save();
                $version = $layer->versions()->create([
                    'number' => ((int) $layer->versions()->max('number')) + 1,
                    'uploaded_by' => $userId,
                    'source_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'source_path' => $path,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'summary' => $collection['summary'],
                ]);
                DB::insert(
                    "INSERT INTO map_features (map_layer_version_id, position, properties, geom)
                    SELECT ?, ordinality, item->'properties', ST_SetSRID(ST_GeomFromGeoJSON((item->'geometry')::text),4326)
                    FROM jsonb_array_elements(?::jsonb) WITH ORDINALITY AS items(item, ordinality)",
                    [$version->id, json_encode($collection['features'], JSON_THROW_ON_ERROR)],
                );

                return $layer->load(['region', 'currentVersion']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    /** @return array{type: string, features: array<int, array<string, mixed>>} */
    public function geoJson(MapLayerVersion $version): array
    {
        $features = $version->features()
            ->select(['id', 'position', 'properties'])
            ->selectRaw('ST_AsGeoJSON(geom, 15, 0) AS geometry')
            ->get()
            ->map(fn (MapFeature $feature): array => [
                'type' => 'Feature',
                'id' => $feature->id,
                'properties' => (object) $feature->properties,
                'geometry' => $feature->geometry,
            ])->all();

        return ['type' => 'FeatureCollection', 'features' => $features];
    }
}
