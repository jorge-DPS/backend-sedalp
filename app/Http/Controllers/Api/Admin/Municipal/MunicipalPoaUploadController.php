<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\ManageMunicipalPoaUploadRequest;
use App\Http\Requests\Municipal\PutMunicipalPoaChunkRequest;
use App\Http\Requests\Municipal\StartMunicipalPoaUploadRequest;
use App\Http\Resources\Municipal\MunicipalPoaUploadResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaUpload;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MunicipalPoaUploadController extends Controller
{
    public function __construct(private readonly MunicipalPoaUploadService $uploads) {}

    public function store(StartMunicipalPoaUploadRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): JsonResponse
    {
        return (new MunicipalPoaUploadResource($this->uploads->start($version, $request->user('api'), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(ManageMunicipalPoaUploadRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaUpload $upload): MunicipalPoaUploadResource
    {
        return new MunicipalPoaUploadResource($this->uploads->owned($upload, $request->user('api')));
    }

    public function chunk(PutMunicipalPoaChunkRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaUpload $upload, int $index): MunicipalPoaUploadResource
    {
        return new MunicipalPoaUploadResource($this->uploads->put($this->uploads->owned($upload, $request->user('api')), $index, $request->file('chunk'), $request->validated('sha256')));
    }

    public function complete(ManageMunicipalPoaUploadRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaUpload $upload): JsonResponse
    {
        return (new MunicipalPoaUploadResource($this->uploads->complete($this->uploads->owned($upload, $request->user('api')))))->response()->setStatusCode($upload->document_id === null ? 202 : 200);
    }

    public function destroy(ManageMunicipalPoaUploadRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaUpload $upload): Response
    {
        $this->uploads->cancel($this->uploads->owned($upload, $request->user('api')));

        return response()->noContent();
    }
}
