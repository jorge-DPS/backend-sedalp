<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\IndexMunicipalPoaVersionRequest;
use App\Http\Requests\Municipal\PublishMunicipalPoaDocumentRequest;
use App\Http\Requests\Municipal\StoreMunicipalPoaVersionRequest;
use App\Http\Resources\Municipal\MunicipalPoaVersionResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MunicipalPoaVersionController extends Controller
{
    public function __construct(private readonly MunicipalPoaVersionService $versions) {}

    public function index(IndexMunicipalPoaVersionRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): AnonymousResourceCollection
    {
        return MunicipalPoaVersionResource::collection($municipalPoa->versions()->filtered($request->validated())
            ->withCount('documents')->orderBy('version_number')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function store(StoreMunicipalPoaVersionRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        return (new MunicipalPoaVersionResource($this->versions->create($municipalPoa)->loadCount('documents')))
            ->response()->setStatusCode(201);
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): MunicipalPoaVersionResource
    {
        return new MunicipalPoaVersionResource($version->loadCount('documents'));
    }

    public function publish(PublishMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): MunicipalPoaVersionResource
    {
        return new MunicipalPoaVersionResource($this->versions->publish($version));
    }
}
