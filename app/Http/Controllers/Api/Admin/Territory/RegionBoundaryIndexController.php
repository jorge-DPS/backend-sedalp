<?php

namespace App\Http\Controllers\Api\Admin\Territory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Territory\RegionBoundaryCollection;
use App\Services\Territory\RegionBoundaryService;

class RegionBoundaryIndexController extends Controller
{
    public function __invoke(RegionBoundaryService $service): RegionBoundaryCollection
    {
        return new RegionBoundaryCollection($service->all());
    }
}
