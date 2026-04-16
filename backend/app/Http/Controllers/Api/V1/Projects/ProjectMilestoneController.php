<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectMilestoneRequest;
use App\Http\Requests\Projects\UpdateProjectMilestoneRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\Projects\ProjectService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function __construct(
        private readonly ProjectService $projectService,
    ) {}

    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::paginated(
            $project->milestones()->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(StoreProjectMilestoneRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $milestone = $project->milestones()->create([
            ...$request->validated(),
            'tenant_id' => $project->tenant_id,
            'status' => 'pending',
        ]);

        $this->projectService->recalculateProgress($project->fresh());

        return ApiResponse::data($milestone->fresh(), 201);
    }

    public function update(UpdateProjectMilestoneRequest $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);

        $milestone->update($request->validated());
        $this->projectService->recalculateProgress($project->fresh());

        return ApiResponse::data($milestone->fresh());
    }

    public function destroy(Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);

        $milestone->delete();
        $this->projectService->recalculateProgress($project->fresh());

        return ApiResponse::noContent();
    }

    public function complete(Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);

        $dependencies = collect($milestone->dependencies ?? []);
        $pendingDependencies = ProjectMilestone::query()
            ->whereIn('id', $dependencies)
            ->whereNotIn('status', ['completed', 'invoiced'])
            ->count();

        if ($pendingDependencies > 0) {
            return ApiResponse::message('Milestone possui dependências pendentes.', 422);
        }

        $milestone->update([
            'status' => 'completed',
            'completed_at' => now(),
            'actual_end' => now()->toDateString(),
        ]);

        $this->projectService->recalculateProgress($project->fresh());

        return ApiResponse::data($milestone->fresh());
    }

    public function generateInvoice(Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);

        $invoice = Invoice::create([
            'tenant_id' => $project->tenant_id,
            'customer_id' => $project->customer_id,
            'created_by' => auth()->id(),
            'invoice_number' => Invoice::nextNumber($project->tenant_id),
            'status' => Invoice::STATUS_DRAFT,
            'total' => $milestone->billing_value ?? 0,
            'items' => [[
                'description' => $milestone->name,
                'amount' => (float) ($milestone->billing_value ?? 0),
            ]],
            'observations' => 'Fatura gerada automaticamente por milestone de projeto.',
        ]);

        $milestone->update([
            'status' => 'invoiced',
            'invoice_id' => $invoice->id,
        ]);

        return ApiResponse::data($milestone->fresh());
    }
}
