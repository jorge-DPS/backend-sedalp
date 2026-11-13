<?php

namespace App\Http\Controllers\Api\Public\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\Public\IndexMunicipalAuthorityRequest;
use App\Http\Requests\Municipal\Public\IndexMunicipalTermRequest;
use App\Http\Resources\Municipal\Public\MunicipalAuthorityResource;
use App\Http\Resources\Municipal\Public\MunicipalTermResource;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Municipal\MunicipalTerm;
use App\Models\Territory\Municipality;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MunicipalTermController extends Controller
{
    public function __construct(private readonly MunicipalPortalService $portal) {}

    public function index(IndexMunicipalTermRequest $request, Municipality $municipality): JsonResponse
    {
        $this->portal->municipality($municipality->id);

        return MunicipalTermResource::collection($municipality->municipalTerms()
            ->whereHas('municipalAuthorities', fn (Builder $query): Builder => $query->published())
            ->withCount(['municipalAuthorities' => fn (Builder $query): Builder => $query->published()])
            ->orderByDesc('start_year')->orderBy('id')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString())
            ->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalTerm $municipalTerm): JsonResponse
    {
        $this->assertVisible($municipality, $municipalTerm);

        return (new MunicipalTermResource($municipalTerm->loadCount(['municipalAuthorities' => fn (Builder $query): Builder => $query->published()])))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function authorities(IndexMunicipalAuthorityRequest $request, Municipality $municipality, MunicipalTerm $municipalTerm): JsonResponse
    {
        $this->assertVisible($municipality, $municipalTerm);

        return MunicipalAuthorityResource::collection($municipalTerm->municipalAuthorities()->published()->filtered($request->validated())
            ->orderByDesc('is_current')->orderBy('full_name')->orderBy('id')
            ->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString())
            ->response()->header('Cache-Control', 'no-store');
    }

    public function showAuthority(Municipality $municipality, MunicipalTerm $municipalTerm, MunicipalAuthority $municipalAuthority): JsonResponse
    {
        $this->assertVisible($municipality, $municipalTerm);
        abort_unless($municipalAuthority->is_published, 404);

        return (new MunicipalAuthorityResource($municipalAuthority))->response()->header('Cache-Control', 'no-store');
    }

    private function assertVisible(Municipality $municipality, MunicipalTerm $term): void
    {
        $this->portal->municipality($municipality->id);
        abort_unless($term->municipalAuthorities()->published()->exists(), 404);
    }
}
