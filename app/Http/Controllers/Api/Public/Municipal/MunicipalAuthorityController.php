<?php

namespace App\Http\Controllers\Api\Public\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\Public\IndexMunicipalAuthorityRequest;
use App\Http\Resources\Municipal\Public\MunicipalAuthorityResource;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalAuthorityPhotoService;
use App\Services\Municipal\Public\MunicipalPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MunicipalAuthorityController extends Controller
{
    public function __construct(private readonly MunicipalPortalService $portal, private readonly MunicipalAuthorityPhotoService $photos) {}

    public function index(IndexMunicipalAuthorityRequest $request, Municipality $municipality): JsonResponse
    {
        $filters = $request->validated();
        $query = $this->portal->authorities($municipality);
        foreach (['role', 'is_current'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }

        return MunicipalAuthorityResource::collection($query->orderByDesc('is_current')->orderBy('full_name')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 15))->withQueryString())->response()->header('Cache-Control', 'no-store');
    }

    public function show(Municipality $municipality, MunicipalAuthority $municipalAuthority): JsonResponse
    {
        return (new MunicipalAuthorityResource($this->portal->authorities($municipality)->findOrFail($municipalAuthority->id)))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function photo(Municipality $municipality, MunicipalAuthority $municipalAuthority): StreamedResponse
    {
        $authority = $this->portal->authorities($municipality)->findOrFail($municipalAuthority->id);

        return Storage::disk('local')->response($this->photos->readablePath($authority), 'authority-'.$authority->id.'.webp', [
            'Content-Type' => 'image/webp', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
