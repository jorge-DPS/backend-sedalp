<?php

namespace App\Http\Controllers\Api\Public\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\Public\IndexMunicipalPoaDocumentRequest;
use App\Http\Resources\Municipal\Public\MunicipalPoaDocumentResource;
use App\Models\Municipal\MunicipalPoa;
use App\Models\Municipal\MunicipalPoaDocument;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalPoaDocumentService;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MunicipalPoaDocumentController extends Controller
{
    public function __construct(private readonly MunicipalPortalService $portal, private readonly MunicipalPoaDocumentService $documents) {}

    public function index(IndexMunicipalPoaDocumentRequest $request, Municipality $municipality, MunicipalPoa $municipalPoa): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->portal->documents($municipality, $municipalPoa);
        foreach (['document_type', 'is_current'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }

        return MunicipalPoaDocumentResource::collection($query->orderBy('version_number')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString())->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): JsonResponse
    {
        return (new MunicipalPoaDocumentResource($this->portal->documents($municipality, $municipalPoa)->findOrFail($document->id)))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function file(Municipality $municipality, MunicipalPoa $municipalPoa, MunicipalPoaDocument $document): StreamedResponse
    {
        $document = $this->portal->documents($municipality, $municipalPoa)->findOrFail($document->id);
        try {
            $this->documents->assertReadable($document);
        } catch (ValidationException) {
            abort(404);
        }

        return Storage::disk('local')->download($document->pdf_path, 'poa-'.$municipalPoa->fiscal_year.'-v'.$document->version_number.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
