<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectTimeEntryRequest;
use App\Http\Requests\Projects\UpdateProjectTimeEntryRequest;
use App\Models\Project;
use App\Models\ProjectTimeEntry;
use App\Services\Projects\ProjectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectTimeEntryController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::paginated(
            $project->timeEntries()
                ->with(['resource.user:id,name', 'milestone:id,name', 'workOrder:id,number,os_number'])
                ->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(StoreProjectTimeEntryRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $entry = $project->timeEntries()->create([
            ...$request->validated(),
            'tenant_id' => $project->tenant_id,
        ]);

        $this->projectService->recalculateSpent($project->fresh());

        return ApiResponse::data($entry->fresh(['resource.user:id,name', 'milestone:id,name', 'workOrder:id,number,os_number']), 201);
    }

    public function update(UpdateProjectTimeEntryRequest $request, Project $project, ProjectTimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('update', $project);

        $timeEntry->update($request->validated());
        $this->projectService->recalculateSpent($project->fresh());

        return ApiResponse::data($timeEntry->fresh(['resource.user:id,name', 'milestone:id,name', 'workOrder:id,number,os_number']));
    }

    public function destroy(Project $project, ProjectTimeEntry $timeEntry): Response
    {
        $this->authorize('update', $project);

        $timeEntry->delete();
        $this->projectService->recalculateSpent($project->fresh());

        return response()->noContent();
    }
}
