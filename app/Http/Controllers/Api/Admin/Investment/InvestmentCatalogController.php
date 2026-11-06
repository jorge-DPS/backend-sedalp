<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Resources\Investment\InvestmentCatalogResource;
use App\Models\Investment\Institution;
use App\Models\Investment\InterventionType;
use App\Models\Investment\ProjectStatus;
use App\Models\Investment\Sector;
use App\Models\Investment\StrategicAxis;
use Illuminate\Http\JsonResponse;

class InvestmentCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'institutions' => InvestmentCatalogResource::collection(Institution::query()->where('active', true)->orderBy('name')->get()),
            'sectors' => InvestmentCatalogResource::collection(Sector::query()->where('active', true)->orderBy('name')->get()),
            'strategic_axes' => InvestmentCatalogResource::collection(StrategicAxis::query()->where('active', true)->orderBy('name')->get()),
            'intervention_types' => InvestmentCatalogResource::collection(InterventionType::query()->where('active', true)->orderBy('name')->get()),
            'project_statuses' => InvestmentCatalogResource::collection(ProjectStatus::query()->where('active', true)->orderBy('name')->get()),
        ]]);
    }
}
