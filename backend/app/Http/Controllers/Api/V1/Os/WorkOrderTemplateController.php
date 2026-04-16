<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreWorkOrderTemplateRequest;
use App\Http\Requests\Os\UpdateWorkOrderTemplateRequest;
use App\Models\WorkOrderTemplate;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderTemplateController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkOrderTemplate::class);

        $templates = WorkOrderTemplate::where('tenant_id', $this->tenantId())
            ->when($request->get('search'), fn ($q, $s) => $q->where('name', 'like', SearchSanitizer::contains($s)))
            ->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        return ApiResponse::paginated($templates);
    }

    public function store(StoreWorkOrderTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', WorkOrderTemplate::class);

        $validated = $request->validated();

        try {
            $template = DB::transaction(fn () => WorkOrderTemplate::create([
                ...$validated,
                'tenant_id' => $this->tenantId(),
                'created_by' => auth()->id(),
            ]));

            return ApiResponse::data($template, 201);
        } catch (\Throwable $e) {
            Log::error('WO template store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar template', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $template = WorkOrderTemplate::where('tenant_id', $this->tenantId())->findOrFail($id);

        $this->authorize('view', $template);

        return ApiResponse::data($template->load('checklist', 'creator:id,name'));
    }

    public function update(UpdateWorkOrderTemplateRequest $request, int $id): JsonResponse
    {
        $template = WorkOrderTemplate::where('tenant_id', $this->tenantId())->findOrFail($id);

        $this->authorize('update', $template);

        try {
            DB::transaction(fn () => $template->update($request->validated()));

            return ApiResponse::data($template->fresh());
        } catch (\Throwable $e) {
            Log::error('WO template update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar template', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $template = WorkOrderTemplate::where('tenant_id', $this->tenantId())->findOrFail($id);

        $this->authorize('delete', $template);

        try {
            DB::transaction(fn () => $template->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('WO template delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir template', 500);
        }
    }
}
