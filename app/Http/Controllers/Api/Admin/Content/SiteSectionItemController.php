<?php

namespace App\Http\Controllers\Api\Admin\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SaveSiteSectionItemRequest;
use App\Http\Resources\Content\SiteSectionItemResource;
use App\Models\Content\SitePage;
use App\Models\Content\SiteSection;
use App\Models\Content\SiteSectionItem;
use App\Services\Content\SiteContentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SiteSectionItemController extends Controller
{
    public function store(SaveSiteSectionItemRequest $request, SitePage $page, SiteSection $section, SiteContentService $service): SiteSectionItemResource
    {
        return new SiteSectionItemResource($service->saveItem($section, null, $request->validated(), $request->user('api')->id));
    }

    public function update(SaveSiteSectionItemRequest $request, SitePage $page, SiteSection $section, SiteSectionItem $item, SiteContentService $service): SiteSectionItemResource
    {
        return new SiteSectionItemResource($service->saveItem($section, $item, $request->validated(), $request->user('api')->id));
    }

    public function destroy(Request $request, SitePage $page, SiteSection $section, SiteSectionItem $item, SiteContentService $service): Response
    {
        $service->deleteItem($section, $item, $request->user('api')->id);

        return response()->noContent();
    }
}
