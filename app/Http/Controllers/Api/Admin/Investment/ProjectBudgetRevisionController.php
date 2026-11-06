<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\IndexProjectBudgetRequest;
use App\Http\Requests\Investment\StoreProjectBudgetRevisionRequest;
use App\Http\Resources\Investment\ProjectBudgetResource;
use App\Http\Resources\Investment\ProjectBudgetRevisionResource;
use App\Models\Investment\Project;
use App\Models\Investment\ProjectBudget;
use App\Services\Investment\ProjectBudgetService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectBudgetRevisionController extends Controller
{
    public function __construct(private readonly ProjectBudgetService $budgets) {}

    public function index(IndexProjectBudgetRequest $request, Project $project, ProjectBudget $budget): AnonymousResourceCollection
    {
        return ProjectBudgetRevisionResource::collection($budget->revisions()->with(['investmentSource', 'previousInvestmentSource'])
            ->orderBy('revision_number')->paginate((int) ($request->validated('per_page') ?? 15))->withQueryString());
    }

    public function store(StoreProjectBudgetRevisionRequest $request, Project $project, ProjectBudget $budget): ProjectBudgetResource
    {
        return new ProjectBudgetResource($this->budgets->revise($budget, $request->validated())
            ->load('investmentSource')->loadCount('revisions'));
    }
}
