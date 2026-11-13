<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\ActivateMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\IndexMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\PublishMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\StoreMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\UpdateMunicipalAuthorityRequest;
use App\Http\Resources\Municipal\MunicipalAuthorityResource;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalAuthorityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MunicipalAuthorityController extends Controller
{
    public function __construct(private readonly MunicipalAuthorityService $authorities) {}

    public function index(IndexMunicipalAuthorityRequest $request, Municipality $municipality, ?MunicipalTerm $municipalTerm = null): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $municipalTerm?->municipalAuthorities() ?? $municipality->municipalAuthorities();
        $query->filtered($filters);

        return MunicipalAuthorityResource::collection($query
            ->orderByDesc('is_current')->orderBy('full_name')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString());
    }

    public function store(StoreMunicipalAuthorityRequest $request, Municipality $municipality, ?MunicipalTerm $municipalTerm = null): JsonResponse
    {
        return (new MunicipalAuthorityResource($this->authorities->create($municipality, $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Municipality $municipality, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($municipalAuthority);
    }

    public function update(UpdateMunicipalAuthorityRequest $request, Municipality $municipality, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($this->authorities->update($municipalAuthority, $request->validated()));
    }

    public function publish(PublishMunicipalAuthorityRequest $request, Municipality $municipality, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($this->authorities->publish($municipalAuthority, $request->boolean('is_published')));
    }

    public function activate(ActivateMunicipalAuthorityRequest $request, Municipality $municipality, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($this->authorities->activateMayor($municipalAuthority, $request->validated()));
    }
}
