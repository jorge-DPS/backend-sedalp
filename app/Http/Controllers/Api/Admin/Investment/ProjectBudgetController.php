<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\IndexProjectBudgetRequest;
use App\Http\Requests\Investment\StoreProjectBudgetRequest;
use App\Http\Resources\Investment\ProjectBudgetResource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Services\Investment\ProjectBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectBudgetController extends Controller
{
    public function __construct(private readonly ProjectBudgetService $budgets) {}

    public function index(IndexProjectBudgetRequest $request, Project $project): AnonymousResourceCollection
    {
        return ProjectBudgetResource::collection($project->budgets()->with('investmentSource')->withCount('revisions')
            ->orderByDesc('fiscal_year')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function store(StoreProjectBudgetRequest $request, Project $project): JsonResponse
    {
        return (new ProjectBudgetResource($this->budgets->create($project, $request->validated())->load('investmentSource')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project, ProjectBudget $budget): ProjectBudgetResource
    {
        return new ProjectBudgetResource($budget->load('investmentSource')->loadCount('revisions'));
    }
}
