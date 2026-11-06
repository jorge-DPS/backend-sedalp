<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\IndexProjectRequest;
use App\Http\Requests\Investment\StoreProjectRequest;
use App\Http\Requests\Investment\UpdateProjectRequest;
use App\Http\Resources\Investment\ProjectResource;
use App\Models\Investment\Project;
use App\Services\Investment\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(IndexProjectRequest $request): AnonymousResourceCollection
    {
        return ProjectResource::collection($this->projects->paginate($request->validated()));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        return (new ProjectResource($this->projects->create($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load(ProjectService::RELATIONS));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->update($project, $request->validated()));
    }

    public function destroy(Project $project): Response
    {
        $this->projects->delete($project);

        return response()->noContent();
    }
}
