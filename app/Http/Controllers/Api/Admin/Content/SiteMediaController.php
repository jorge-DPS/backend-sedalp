<?php

namespace App\Http\Controllers\Api\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\IndexSiteMediaRequest;
use App\Http\Requests\Content\StoreSiteMediaRequest;
use App\Http\Resources\Content\SiteMediaResource;
use App\Models\Content\SiteMedia;
use App\Services\Content\SiteMediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteMediaController extends Controller
{
    public function index(IndexSiteMediaRequest $request): AnonymousResourceCollection
    {
        return SiteMediaResource::collection(SiteMedia::query()
            ->when($request->validated('profile'), fn (Builder $query, string $profile): Builder => $query->where('profile', $profile))
            ->withExists(['desktopSections', 'mobileSections'])->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(StoreSiteMediaRequest $request, SiteMediaService $service): SiteMediaResource
    {
        return new SiteMediaResource($service->store($request->file('image'), $request->validated('profile'), $request->user('api')->id));
    }

    public function destroy(SiteMedia $media, SiteMediaService $service): Response
    {
        $service->delete($media);

        return response()->noContent();
    }
}
