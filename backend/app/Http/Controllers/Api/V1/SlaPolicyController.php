<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreSlaPolicyRequest;
use App\Http\Requests\Os\UpdateSlaPolicyRequest;
use App\Http\Resources\SlaPolicyResource;
use App\Models\SlaPolicy;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlaPolicyController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SlaPolicy::class);
        $tenantId = $this->tenantId();

        $policies = SlaPolicy::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($policies->map(fn ($p) => new SlaPolicyResource($p)));
    }

    public function store(StoreSlaPolicyRequest $request): JsonResponse
    {
        $this->authorize('create', SlaPolicy::class);
        $tenantId = $this->tenantId();
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;

        try {
            $policy = DB::transaction(fn () => SlaPolicy::create($data));

            return ApiResponse::data(new SlaPolicyResource($policy), 201);
        } catch (\Throwable $e) {
            Log::error('SlaPolicy store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar politica SLA', 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $policy = SlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('view', $policy);

        return ApiResponse::data(new SlaPolicyResource($policy));
    }

    public function update(UpdateSlaPolicyRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $policy = SlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('update', $policy);
        $data = $request->validated();

        $policy->update($data);

        return ApiResponse::data(new SlaPolicyResource($policy));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $policy = SlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);
        $this->authorize('delete', $policy);

        try {
            DB::transaction(fn () => $policy->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('SlaPolicy destroy failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir politica SLA', 500);
        }
    }
}
