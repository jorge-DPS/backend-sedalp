<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Territory\RegionBoundaryResource;
use App\Models\Territory\Region;
use App\Services\Territory\RegionBoundaryService;

class RegionBoundaryController extends Controller
{
    public function __invoke(Region $region, RegionBoundaryService $service): RegionBoundaryResource
    {
        return new RegionBoundaryResource($service->find($region));
    }
}
