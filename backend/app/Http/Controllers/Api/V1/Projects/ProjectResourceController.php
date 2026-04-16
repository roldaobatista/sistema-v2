<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectResourceRequest;
use App\Http\Requests\Projects\UpdateProjectResourceRequest;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectResourceController extends Controller
{
    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::paginated(
            $project->resources()
                ->with(['user:id,name'])
                ->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(StoreProjectResourceRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $resource = $project->resources()->create([
            ...$request->validated(),
            'tenant_id' => $project->tenant_id,
            'total_hours_logged' => 0,
        ]);

        return ApiResponse::data($resource->fresh(['user:id,name']), 201);
    }

    public function update(UpdateProjectResourceRequest $request, Project $project, ProjectResource $resource): JsonResponse
    {
        $this->authorize('update', $project);

        $resource->update($request->validated());

        return ApiResponse::data($resource->fresh(['user:id,name']));
    }

    public function destroy(Project $project, ProjectResource $resource): Response
    {
        $this->authorize('update', $project);

        $resource->delete();

        return response()->noContent();
    }
}
