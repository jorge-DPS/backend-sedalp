<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\IndexMunicipalPoaDocumentRequest;
use App\Http\Requests\Municipal\PublishMunicipalPoaDocumentRequest;
use App\Http\Requests\Municipal\UploadMunicipalPoaDocumentRequest;
use App\Http\Resources\Municipal\MunicipalPoaDocumentResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Municipal\MunicipalPoaVersion;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaDocumentService;
use App\Services\Municipal\MunicipalPoaVersionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MunicipalPoaDocumentController extends Controller
{
    public function __construct(private readonly MunicipalPoaDocumentService $documents, private readonly MunicipalPoaVersionService $versions) {}

    public function index(IndexMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): AnonymousResourceCollection
    {
        return MunicipalPoaDocumentResource::collection($municipalPoa->documents()
            ->whereHas('version', fn (Builder $query): Builder => $query->filtered($request->validated()))
            ->orderBy('municipal_poa_documents.id')
            ->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function store(UploadMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        $version = $municipalPoa->versions()->findOrFail($request->validated('municipal_poa_version_id'));

        return $this->upload($request, $version);
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): MunicipalPoaDocumentResource
    {
        return new MunicipalPoaDocumentResource($document);
    }

    public function publish(PublishMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): MunicipalPoaDocumentResource
    {
        abort_unless($document->version->documents()->count() === 1, 409,
            'Publique explícitamente la versión completa mediante versions/{version}/publication.');
        $this->versions->publish($document->version);

        return new MunicipalPoaDocumentResource($document->refresh());
    }

    public function file(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): StreamedResponse
    {
        $this->documents->assertReadable($document);

        return Storage::disk('local')->download($document->pdf_path,
            'poa-'.$municipalPoa->fiscal_year.'-v'.$document->version_number.'-'.$document->id.'.pdf', [
                'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function versionIndex(IndexMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): AnonymousResourceCollection
    {
        return MunicipalPoaDocumentResource::collection($version->documents()
            ->whereHas('version', fn (Builder $query): Builder => $query->filtered($request->validated()))
            ->orderBy('id')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function versionStore(UploadMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version): JsonResponse
    {
        return $this->upload($request, $version);
    }

    public function versionShow(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaDocument $document): MunicipalPoaDocumentResource
    {
        return new MunicipalPoaDocumentResource($document);
    }

    public function versionFile(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaVersion $version, MunicipalPoaDocument $document): StreamedResponse
    {
        return $this->file($municipality, $municipalPoa, $document);
    }

    private function upload(UploadMunicipalPoaDocumentRequest $request, MunicipalPoaVersion $version): JsonResponse
    {
        return (new MunicipalPoaDocumentResource($this->documents->upload(
            $version, $request->file('pdf'), Arr::except($request->validated(), ['pdf', 'municipal_poa_version_id']),
        )))->response()->setStatusCode(201);
    }
}
