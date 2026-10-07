<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Territory\IndexMunicipalityRequest;
use App\Http\Requests\Territory\StoreMunicipalityRequest;
use App\Http\Requests\Territory\UpdateMunicipalityRequest;
use App\Http\Resources\Territory\MunicipalityResource;
use App\Models\Territory\Municipality;
use App\Services\Territory\MunicipalityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MunicipalityController extends Controller
{
    public function __construct(private readonly MunicipalityService $municipalityService) {}

    public function index(IndexMunicipalityRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $municipalities = Municipality::query()
            ->with(['region', 'province'])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->whereLike('name', '%'.$filters['search'].'%')
                        ->orWhereLike('official_code', '%'.$filters['search'].'%');
                });
            })
            ->when(array_key_exists('active', $filters), fn (Builder $query): Builder => $query->where('active', $filters['active']))
            ->when(isset($filters['region_id']), fn (Builder $query): Builder => $query->where('region_id', $filters['region_id']))
            ->when(isset($filters['province_id']), fn (Builder $query): Builder => $query->where('province_id', $filters['province_id']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return MunicipalityResource::collection($municipalities);
    }

    public function store(StoreMunicipalityRequest $request): JsonResponse
    {
        $municipality = $this->municipalityService->create($request->validated());

        return (new MunicipalityResource($municipality))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Municipality $municipality): MunicipalityResource
    {
        return new MunicipalityResource($municipality->load(['region', 'province']));
    }

    public function update(UpdateMunicipalityRequest $request, Municipality $municipality): MunicipalityResource
    {
        return new MunicipalityResource(
            $this->municipalityService->update($municipality, $request->validated())
        );
    }

    public function destroy(Municipality $municipality): Response
    {
        $municipality->delete();

        return response()->noContent();
    }
}
