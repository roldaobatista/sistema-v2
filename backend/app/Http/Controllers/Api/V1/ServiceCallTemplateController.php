<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCall\StoreServiceCallTemplateRequest;
use App\Http\Requests\ServiceCall\UpdateServiceCallTemplateRequest;
use App\Http\Resources\ServiceCallTemplateResource;
use App\Models\ServiceCallTemplate;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ServiceCallTemplateController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ServiceCallTemplate::class);
        $list = ServiceCallTemplate::where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($list->map(fn ($t) => new ServiceCallTemplateResource($t)));
    }

    public function activeList(): JsonResponse
    {
        $this->authorize('viewAny', ServiceCallTemplate::class);
        $list = ServiceCallTemplate::where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'priority', 'observations', 'equipment_ids', 'tenant_id', 'is_active', 'created_at', 'updated_at']);

        return ApiResponse::data($list->map(fn ($t) => new ServiceCallTemplateResource($t)));
    }

    public function store(StoreServiceCallTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceCallTemplate::class);
        $validated = $request->validated();

        try {
            $template = ServiceCallTemplate::create([
                ...$validated,
                'tenant_id' => $this->tenantId(),
            ]);

            return ApiResponse::data(new ServiceCallTemplateResource($template), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCallTemplate create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar template', 500);
        }
    }

    public function update(UpdateServiceCallTemplateRequest $request, ServiceCallTemplate $serviceCallTemplate): JsonResponse
    {
        $this->authorize('update', $serviceCallTemplate);
        if ((int) $serviceCallTemplate->tenant_id !== $this->tenantId()) {
            abort(403);
        }
        $validated = $request->validated();

        try {
            $serviceCallTemplate->update($validated);

            return ApiResponse::data(new ServiceCallTemplateResource($serviceCallTemplate->fresh()));
        } catch (\Throwable $e) {
            Log::error('ServiceCallTemplate update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar template', 500);
        }
    }

    public function destroy(ServiceCallTemplate $serviceCallTemplate): JsonResponse
    {
        $this->authorize('delete', $serviceCallTemplate);

        if ((int) $serviceCallTemplate->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        try {
            $serviceCallTemplate->delete();

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ServiceCallTemplate delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir template', 500);
        }
    }
}
