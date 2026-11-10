<?php

namespace App\Http\Controllers\Api\Admin\Municipal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Municipal\StoreMunicipalAuthorityPhotoRequest;
use App\Http\Resources\Municipal\MunicipalAuthorityResource;
use App\Models\Municipal\MunicipalAuthority;
use App\Models\Territory\Municipality;
use App\Services\Municipal\MunicipalAuthorityPhotoService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MunicipalAuthorityPhotoController extends Controller
{
    public function __construct(private readonly MunicipalAuthorityPhotoService $photos) {}

    public function store(StoreMunicipalAuthorityPhotoRequest $request, Municipality $municipality, MunicipalAuthority $municipalAuthority): MunicipalAuthorityResource
    {
        return new MunicipalAuthorityResource($this->photos->store($municipalAuthority, $request->file('photo')));
    }

    public function show(Municipality $municipality, MunicipalAuthority $municipalAuthority): StreamedResponse
    {
        return Storage::disk('local')->response($this->photos->readablePath($municipalAuthority), 'authority-'.$municipalAuthority->id.'.webp', [
            'Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
