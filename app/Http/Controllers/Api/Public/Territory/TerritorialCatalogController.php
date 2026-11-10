<?php

namespace App\Http\Controllers\Api\Public\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\Public\IndexTerritorialCatalogRequest;
use App\Http\Resources\Territory\Public\MunicipalityResource;
use App\Http\Resources\Territory\Public\TerritorialCatalogResource;
use App\Models\Territory\Municipality;
use App\Models\Territory\Province;
use App\Models\Territory\Region;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class TerritorialCatalogController extends Controller
{
    public function regions(IndexTerritorialCatalogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Region::query()->where('active', true)
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->orderBy('name')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return TerritorialCatalogResource::collection($query)->response()->header('Cache-Control', 'no-store');
    }

    public function provinces(IndexTerritorialCatalogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Province::query()->where('active', true)
            ->when(isset($filters['region_id']), fn (Builder $query): Builder => $query->whereHas('municipalities', fn (Builder $query): Builder => $query->publiclyAvailable()->where('region_id', $filters['region_id'])))
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->orderBy('name')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return TerritorialCatalogResource::collection($query)->response()->header('Cache-Control', 'no-store');
    }

    public function municipalities(IndexTerritorialCatalogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $query = Municipality::query()->publiclyAvailable()->with(['region', 'province'])
            ->when(isset($filters['region_id']), fn (Builder $query): Builder => $query->where('region_id', $filters['region_id']))
            ->when(isset($filters['province_id']), fn (Builder $query): Builder => $query->where('province_id', $filters['province_id']))
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('name', '%'.$filters['search'].'%'))
            ->orderBy('name')->orderBy('id')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return MunicipalityResource::collection($query)->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalPortalService $portal): JsonResponse
    {
        return (new MunicipalityResource($portal->municipality($municipality->id)))->response()->header('Cache-Control', 'no-store');
    }
}
