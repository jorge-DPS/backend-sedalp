<?php

namespace App\Http\Controllers\Api\Public\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\Public\IndexInstitutionRequest;
use App\Http\Resources\Investment\Public\InstitutionResource;
use App\Models\Investment\Institution;
use App\Services\Investment\Public\DashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class InstitutionController extends Controller
{
    public function __invoke(IndexInstitutionRequest $request, DashboardService $dashboard): JsonResponse
    {
        $published = fn (Builder $query): Builder => $query->publiclyAvailable();
        $institutions = Institution::withTrashed()->select(['id', 'name', 'code'])
            ->whereHas('projects', $published)->withCount(['projects' => $published])
            ->orderBy('name')->orderBy('id')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString();

        return InstitutionResource::collection($institutions)->additional(['meta' => $dashboard->metadata([])])
            ->response()->header('Cache-Control', 'no-store');
    }
}
