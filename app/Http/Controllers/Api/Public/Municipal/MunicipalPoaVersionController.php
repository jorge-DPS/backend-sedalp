<?php

namespace App\Http\Controllers\Api\Public\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\Public\IndexMunicipalPoaVersionRequest;
use App\Http\Resources\Municipal\Public\MunicipalPoaVersionResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Http\JsonResponse;

class MunicipalPoaVersionController extends Controller
{
    public function __construct(private readonly MunicipalPortalService $portal) {}

    public function index(IndexMunicipalPoaVersionRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        $this->portal->poas($municipality)->findOrFail($municipalPoa->id);

        return MunicipalPoaVersionResource::collection($municipalPoa->versions()->publiclyAvailable()->filtered($request->validated())
            ->withCount('documents')->orderBy('version_number')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString())
            ->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): JsonResponse
    {
        $this->portal->poas($municipality)->findOrFail($municipalPoa->id);
        abort_unless($version->isPubliclyAvailable(), 404);

        return (new MunicipalPoaVersionResource($version->loadCount('documents')))->response()->header('Cache-Control', 'no-store');
    }
}
