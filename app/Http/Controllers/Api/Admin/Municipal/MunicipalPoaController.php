<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\IndexMunicipalPoaRequest;
use App\Http\Requests\Municipal\StoreMunicipalPoaRequest;
use App\Http\Requests\Municipal\UpdateMunicipalPoaRequest;
use App\Http\Resources\Municipal\MunicipalPoaResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MunicipalPoaController extends Controller
{
    public function __construct(private readonly MunicipalPoaService $poas) {}

    public function index(IndexMunicipalPoaRequest $request, Municipality $municipality): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return MunicipalPoaResource::collection($municipality->municipalPoas()->withCount('documents')
            ->when(isset($filters['fiscal_year']), fn (Builder $query): Builder => $query->where('fiscal_year', $filters['fiscal_year']))
            ->when(filled($filters['search'] ?? null), fn (Builder $query): Builder => $query->whereLike('title', '%'.$filters['search'].'%'))
            ->orderByDesc('fiscal_year')->paginate((int) ($filters['per_page'] ?? 15))->withQueryString());
    }

    public function store(StoreMunicipalPoaRequest $request, Municipality $municipality): JsonResponse
    {
        return (new MunicipalPoaResource($this->poas->create($municipality, $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa): MunicipalPoaResource
    {
        return new MunicipalPoaResource($municipalPoa->loadCount('documents'));
    }

    public function update(UpdateMunicipalPoaRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): MunicipalPoaResource
    {
        return new MunicipalPoaResource($this->poas->update($municipalPoa, $request->validated()));
    }
}
