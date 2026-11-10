<?php

namespace App\Http\Controllers\Api\Admin\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\PublishProjectRequest;
use App\Http\Resources\Investment\ProjectResource;
use App\Models\Investment\Project;
use App\Services\Investment\ProjectService;

class ProjectPublicationController extends Controller
{
    public function __construct(private readonly ProjectService $projects) {}

    public function __invoke(PublishProjectRequest $request, Project $project): ProjectResource
    {
        return new ProjectResource($this->projects->publish($project, $request->boolean('is_published')));
    }
}
