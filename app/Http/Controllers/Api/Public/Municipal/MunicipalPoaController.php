<?php

namespace App\Http\Controllers\Api\Public\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\Public\IndexMunicipalPoaRequest;
use App\Http\Resources\Municipal\Public\MunicipalPoaResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Territory\Municipality;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MunicipalPoaController extends Controller
{
    public function __construct(private readonly MunicipalPortalService $portal) {}

    public function index(IndexMunicipalPoaRequest $request, Municipality $municipality): JsonResponse
    {
        $filters = $request->validated();
        $poas = $this->portal->poas($municipality)
            ->when(isset($filters['fiscal_year']), fn (Builder $query): Builder => $query->where('fiscal_year', $filters['fiscal_year']))
            ->orderByDesc('fiscal_year')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return MunicipalPoaResource::collection($poas)->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        return (new MunicipalPoaResource($this->portal->poas($municipality)->findOrFail($municipalPoa->id)))
            ->response()->header('Cache-Control', 'no-store');
    }
}
