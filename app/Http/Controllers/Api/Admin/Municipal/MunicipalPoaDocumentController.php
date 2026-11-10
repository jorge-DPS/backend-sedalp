<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\IndexMunicipalPoaDocumentRequest;
use App\Http\Requests\Municipal\PublishMunicipalPoaDocumentRequest;
use App\Http\Requests\Municipal\UploadMunicipalPoaDocumentRequest;
use App\Http\Resources\Municipal\MunicipalPoaDocumentResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MunicipalPoaDocumentController extends Controller
{
    public function __construct(private readonly MunicipalPoaDocumentService $documents) {}

    public function index(IndexMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = $municipalPoa->documents();
        foreach (['document_type', 'is_current'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (array_key_exists('published', $filters)) {
            if ($filters['published']) {
                $query->publiclyAvailable();
            } else {
                $query->where(fn (Builder $query): Builder => $query->whereNull('published_at')->orWhere('published_at', '>', now()));
            }
        }

        return MunicipalPoaDocumentResource::collection($query->orderBy('version_number')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString());
    }

    public function store(UploadMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        return (new MunicipalPoaDocumentResource($this->documents->upload(
            $municipalPoa, $request->file('pdf'), Arr::except($request->validated(), ['pdf']),
        )))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): MunicipalPoaDocumentResource
    {
        return new MunicipalPoaDocumentResource($document);
    }

    public function publish(PublishMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): MunicipalPoaDocumentResource
    {
        return new MunicipalPoaDocumentResource($this->documents->publish($document));
    }

    public function file(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): StreamedResponse
    {
        $this->documents->assertReadable($document);

        return Storage::disk('local')->download($document->pdf_path, 'poa-'.$municipalPoa->fiscal_year.'-v'.$document->version_number.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
