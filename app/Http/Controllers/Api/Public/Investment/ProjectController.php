<?php

namespace App\Http\Controllers\Api\Public\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\Public\IndexProjectRequest;
use App\Http\Resources\Investment\Public\ProjectResource;
use App\Models\Investment\Project;
use App\Services\Investment\Public\ProjectService;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(IndexProjectRequest $request): JsonResponse
    {
        return ProjectResource::collection($this->projects->paginate($request->validated()))
            ->response()->header('Cache-Control', 'no-store');
    }

    public function show(IndexProjectRequest $request, Project $project): JsonResponse
    {
        return (new ProjectResource($this->projects->find($project->id, $request->validated())))
            ->response()->header('Cache-Control', 'no-store');
    }
}
