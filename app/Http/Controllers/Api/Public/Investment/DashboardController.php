<?php

namespace App\Http\Controllers\Api\Public\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\Public\DashboardRequest;
use App\Services\Investment\Public\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function summary(DashboardRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json(['data' => $this->dashboard->summary($filters), 'meta' => $this->dashboard->metadata($filters)], 200, ['Cache-Control' => 'no-store']);
    }

    public function distributions(DashboardRequest $request): JsonResponse
    {
        $filters = $request->validated();

        return response()->json(['data' => $this->dashboard->distributions($filters), 'meta' => $this->dashboard->metadata($filters)], 200, ['Cache-Control' => 'no-store']);
    }

    public function territories(DashboardRequest $request, string $territoryType): JsonResponse
    {
        $filters = $request->validated();
        $result = $this->dashboard->territories($territoryType, $filters);

        return JsonResource::collection($result['results'])
            ->additional(['unassigned' => $result['unassigned'], 'meta' => $this->dashboard->metadata($filters)])
            ->response()->header('Cache-Control', 'no-store');
    }
}
