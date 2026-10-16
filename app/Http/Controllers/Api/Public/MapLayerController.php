<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\PublicMapLayerRequest;
use App\Http\Resources\Territory\PublicMapLayerResource;
use App\Models\Territory\MapFeature;
use App\Models\Territory\MapLayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MapLayerController extends Controller
{
    public function index(PublicMapLayerRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $layers = MapLayer::query()->publiclyVisible()->with(['region', 'publishedVersion'])
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->when(filled($filters['category'] ?? null), fn (Builder $query): Builder => $query->where('category', $filters['category']))
            ->when(filled($filters['region_id'] ?? null), fn (Builder $query): Builder => $query->where('region_id', $filters['region_id']))
            ->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return PublicMapLayerResource::collection($layers)->response()->header('Cache-Control', 'no-store');
    }

    public function show(int $mapLayer): JsonResponse
    {
        $layer = MapLayer::query()->publiclyVisible()->with(['region', 'publishedVersion'])->findOrFail($mapLayer);

        return (new PublicMapLayerResource($layer))->response()->header('Cache-Control', 'no-store');
    }

    public function geoJson(int $mapLayer): JsonResponse
    {
        $layer = MapLayer::query()->publiclyVisible()->findOrFail($mapLayer);

        return $this->geometryResponse($this->features($layer));
    }

    public function boundaries(): JsonResponse
    {
        $features = [];
        foreach (MapLayer::query()->publiclyVisible()->where('kind', 'boundary')->orderBy('id')->cursor() as $layer) {
            array_push($features, ...$this->features($layer));
        }

        return $this->geometryResponse($features);
    }

    /** @return list<array<string, mixed>> */
    private function features(MapLayer $layer): array
    {
        return MapFeature::query()->where('map_layer_version_id', $layer->published_version_id)
            ->select(['id', 'position'])->selectRaw('ST_AsGeoJSON(geom, 15, 0) AS geometry')
            ->orderBy('position')->get()->map(fn (MapFeature $feature): array => [
                'type' => 'Feature',
                'id' => $feature->id,
                'properties' => [
                    'layer_id' => $layer->id, 'name' => $layer->name,
                    'category' => $layer->category, 'region_id' => $layer->region_id,
                ],
                'geometry' => $feature->geometry,
            ])->all();
    }

    /** @param list<array<string, mixed>> $features */
    private function geometryResponse(array $features): JsonResponse
    {
        return response()->json(['type' => 'FeatureCollection', 'features' => $features], 200,
            ['Content-Type' => 'application/geo+json', 'Cache-Control' => 'no-store']);
    }
}
