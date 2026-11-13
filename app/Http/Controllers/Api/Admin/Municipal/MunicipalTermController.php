<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\IndexMunicipalTermRequest;
use App\Http\Requests\Municipal\StoreMunicipalTermRequest;
use App\Http\Requests\Municipal\UpdateMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\UpdateMunicipalTermRequest;
use App\Http\Resources\Municipal\MunicipalAuthorityResource;
use App\Http\Resources\Municipal\MunicipalTermResource;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalAuthorityService;
use App\Services\Municipal\MunicipalTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MunicipalTermController extends Controller
{
    public function __construct(private readonly MunicipalTermService $terms, private readonly MunicipalAuthorityService $authorities) {}

    public function index(IndexMunicipalTermRequest $request, Municipality $municipality): AnonymousResourceCollection
    {
        return MunicipalTermResource::collection($municipality->municipalTerms()->withCount('municipalAuthorities')
            ->orderByDesc('start_year')->orderBy('id')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function store(StoreMunicipalTermRequest $request, Municipality $municipality): JsonResponse
    {
        return (new MunicipalTermResource($this->terms->save($municipality, $request->validated())->loadCount('municipalAuthorities')))
            ->response()->setStatusCode(201);
    }

    public function show(Municipality $municipality, MunicipalTerm $municipalTerm): MunicipalTermResource
    {
        return new MunicipalTermResource($municipalTerm->loadCount('municipalAuthorities'));
    }

    public function update(UpdateMunicipalTermRequest $request, Municipality $municipality, MunicipalTerm $municipalTerm): MunicipalTermResource
    {
        return new MunicipalTermResource($this->terms->save($municipality, $request->validated(), $municipalTerm)->loadCount('municipalAuthorities'));
    }

    public function showAuthority(Municipality $municipality, MunicipalTerm $municipalTerm, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($municipalAuthority);
    }

    public function updateAuthority(UpdateMunicipalAuthorityRequest $request, Municipality $municipality, MunicipalTerm $municipalTerm, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($this->authorities->update($municipalAuthority, $request->validated()));
    }
}
