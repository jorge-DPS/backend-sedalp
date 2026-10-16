<?php

namespace App\Http\Controllers\Api\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\Content\SitePageResource;
use App\Models\Content\SitePage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SitePageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SitePageResource::collection(SitePage::query()->withCount('sections')->orderBy('id')->get())
            ->additional(['meta' => [
                'limits' => ['title' => 500, 'eyebrow' => 200, 'subtitle' => 1000, 'description' => 10000, 'image_alt' => 500],
                'image_profiles' => config('content.images'),
                'max_upload_kb' => config('content.max_upload_kb'),
            ]]);
    }

    public function show(SitePage $page): SitePageResource
    {
        return new SitePageResource($page->load(['sections.items', 'sections.desktopMedia', 'sections.mobileMedia']));
    }
}
