<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\IndexProvinceRequest;
use App\Http\Requests\Territory\StoreProvinceRequest;
use App\Http\Requests\Territory\UpdateProvinceRequest;
use App\Http\Resources\Territory\ProvinceResource;
use App\Models\Territory\Province;
use App\Services\Territory\TerritorialCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProvinceController extends Controller
{
    public function __construct(private readonly TerritorialCatalogService $catalogService) {}

    public function index(IndexProvinceRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $provinces = Province::query()
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

        return ProvinceResource::collection($provinces);
    }

    public function store(StoreProvinceRequest $request): JsonResponse
    {
        $province = Province::create($request->validated());

        return (new ProvinceResource($province))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Province $province): ProvinceResource
    {
        return new ProvinceResource($province->loadCount('municipalities'));
    }

    public function update(UpdateProvinceRequest $request, Province $province): ProvinceResource
    {
        $province->update($request->validated());

        return new ProvinceResource($province->refresh());
    }

    public function destroy(Province $province): Response
    {
        $this->catalogService->delete($province);

        return response()->noContent();
    }
}
