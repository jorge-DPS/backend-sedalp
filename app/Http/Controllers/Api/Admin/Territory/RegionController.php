<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\IndexRegionRequest;
use App\Http\Requests\Territory\StoreRegionRequest;
use App\Http\Requests\Territory\UpdateRegionRequest;
use App\Http\Resources\Territory\RegionResource;
use App\Models\Territory\Region;
use App\Services\Territory\TerritorialCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RegionController extends Controller
{
    public function __construct(private readonly TerritorialCatalogService $catalogService) {}

    public function index(IndexRegionRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $regions = Region::query()
            ->withCount('municipalities')
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->whereLike('name', '%'.$filters['search'].'%')
                        ->orWhereLike('code', '%'.$filters['search'].'%');
                });
            })
            ->when(array_key_exists('active', $filters), fn (Builder $query): Builder => $query->where('active', $filters['active']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return RegionResource::collection($regions);
    }

    public function store(StoreRegionRequest $request): JsonResponse
    {
        $region = Region::create($request->validated());

        return (new RegionResource($region))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Region $region): RegionResource
    {
        return new RegionResource($region->loadCount('municipalities'));
    }

    public function update(UpdateRegionRequest $request, Region $region): RegionResource
    {
        $region->update($request->validated());

        return new RegionResource($region->refresh());
    }

    public function destroy(Region $region): Response
    {
        $this->catalogService->delete($region);

        return response()->noContent();
    }
}
