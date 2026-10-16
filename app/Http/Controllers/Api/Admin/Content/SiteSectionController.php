<?php

namespace App\Http\Controllers\Api\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpdateSiteSectionRequest;
use App\Http\Resources\Content\SiteSectionResource;
use App\Models\Content\SitePage;
use App\Models\Content\SiteSection;
use App\Services\Content\SiteContentService;

class SiteSectionController extends Controller
{
    public function update(UpdateSiteSectionRequest $request, SitePage $page, SiteSection $section, SiteContentService $service): SiteSectionResource
    {
        return new SiteSectionResource($service->updateSection($section, $request->validated(), $request->user('api')->id));
    }
}
