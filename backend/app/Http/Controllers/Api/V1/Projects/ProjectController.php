<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use App\Services\Projects\ProjectService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Require explicit view permission mapping
        $this->authorize('viewAny', Project::class);

        $query = Project::with([
            'customer:id,name,business_name',
            'crmDeal:id,title,status,value',
            'manager:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->resolvedTenantId();
        $data['created_by'] = $request->user()->id;
        $data['code'] = Project::generateCode();
        $data['spent'] = $data['spent'] ?? 0;
        $data['progress_percent'] = $data['progress_percent'] ?? 0;

        $project = $this->projectService->create($data);
        $project->load(['customer:id,name,business_name', 'crmDeal:id,title,status,value', 'manager:id,name']);

        return ApiResponse::data($project, 201, ['message' => 'Projeto criado com sucesso.']);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['customer', 'crmDeal', 'manager', 'workOrders']);

        return ApiResponse::data($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return ApiResponse::data($project->fresh(['customer:id,name,business_name', 'crmDeal:id,title,status,value', 'manager:id,name']), 200, ['message' => 'Projeto atualizado.']);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->forceDelete();

        return ApiResponse::message('Projeto excluído com sucesso.');
    }

    public function start(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return ApiResponse::data($this->projectService->transition($project, 'active'));
    }

    public function pause(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return ApiResponse::data($this->projectService->transition($project, 'on_hold'));
    }

    public function resume(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return ApiResponse::data($this->projectService->transition($project, 'active'));
    }

    public function complete(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return ApiResponse::data($this->projectService->transition($project, 'completed'));
    }

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        return ApiResponse::data($this->projectService->dashboard($this->resolvedTenantId()));
    }

    public function gantt(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::data($this->projectService->gantt($project));
    }
}
