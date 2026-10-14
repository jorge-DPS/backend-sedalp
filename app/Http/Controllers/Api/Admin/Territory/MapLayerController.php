<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\IndexMapLayerRequest;
use App\Http\Requests\Territory\StoreMapLayerRequest;
use App\Http\Requests\Territory\UploadMapLayerRequest;
use App\Http\Resources\Territory\MapLayerResource;
use App\Http\Resources\Territory\MapLayerVersionResource;
use App\Models\Territory\MapLayer;
use App\Services\Territory\MapFileService;
use App\Services\Territory\MapLayerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MapLayerController extends Controller
{
    public function __construct(private readonly MapLayerService $layers) {}

    public function index(IndexMapLayerRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return MapLayerResource::collection(MapLayer::query()->with(['region', 'currentVersion'])
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->when(filled($filters['region_id'] ?? null), fn (Builder $query): Builder => $query->where('region_id', $filters['region_id']))
            ->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function preview(UploadMapLayerRequest $request, MapFileService $files): JsonResponse
    {
        return response()->json(['data' => $files->read($request->file('file'), $request->boolean('repair_geometries'))]);
    }

    public function store(StoreMapLayerRequest $request): JsonResponse
    {
        return (new MapLayerResource($this->layers->save($request->file('file'), $request->validated(), $request->user('api')->id)))
            ->response()->setStatusCode(201);
    }

    public function update(UploadMapLayerRequest $request, MapLayer $mapLayer): JsonResponse
    {
        return (new MapLayerResource($this->layers->save($request->file('file'), $request->validated(), $request->user('api')->id, $mapLayer)))
            ->response()->setStatusCode(201);
    }

    public function geoJson(MapLayer $mapLayer): JsonResponse
    {
        return response()->json($this->layers->geoJson($mapLayer->currentVersion()->firstOrFail()), 200, ['Content-Type' => 'application/geo+json']);
    }

    public function versions(MapLayer $mapLayer): AnonymousResourceCollection
    {
        return MapLayerVersionResource::collection($mapLayer->versions()->orderByDesc('number')->paginate(20));
    }
}
