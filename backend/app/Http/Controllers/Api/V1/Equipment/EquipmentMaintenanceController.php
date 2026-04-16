<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreEquipmentMaintenanceRequest;
use App\Http\Requests\Equipment\UpdateEquipmentMaintenanceRequest;
use App\Models\EquipmentMaintenance;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EquipmentMaintenanceController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipmentMaintenance::class);
        $query = EquipmentMaintenance::where('tenant_id', $this->tenantId($request))
            ->with(['equipment:id,code,type,brand,model', 'performer:id,name', 'workOrder:id,number'])
            ->orderByDesc('created_at');

        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->equipment_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $search = SearchSanitizer::contains($request->search);
            $query->where('description', 'like', $search);
        }

        return ApiResponse::paginated($query->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function show(Request $request, EquipmentMaintenance $equipmentMaintenance): JsonResponse
    {
        $this->authorize('view', $equipmentMaintenance);

        $equipmentMaintenance->load([
            'equipment:id,code,type,brand,model,serial_number',
            'performer:id,name',
            'workOrder:id,number',
        ]);

        return ApiResponse::data($equipmentMaintenance);
    }

    public function store(StoreEquipmentMaintenanceRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();
        $validated['tenant_id'] = $tenantId;
        $validated['performed_by'] = $request->user()->id;

        try {
            $maintenance = EquipmentMaintenance::create($validated);

            return ApiResponse::data($maintenance->load('equipment:id,code,type,brand,model'), 201);
        } catch (\Throwable $e) {
            Log::error('EquipmentMaintenance store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar manutenção.', 500);
        }
    }

    public function update(UpdateEquipmentMaintenanceRequest $request, EquipmentMaintenance $equipmentMaintenance): JsonResponse
    {
        $this->authorize('update', $equipmentMaintenance);
        $validated = $request->validated();

        try {
            $equipmentMaintenance->update($validated);

            return ApiResponse::data($equipmentMaintenance->fresh()->load('equipment:id,code,type,brand,model'));
        } catch (\Throwable $e) {
            Log::error('EquipmentMaintenance update failed', ['id' => $equipmentMaintenance->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar manutenção.', 500);
        }
    }

    public function destroy(Request $request, EquipmentMaintenance $equipmentMaintenance): JsonResponse
    {
        $this->authorize('delete', $equipmentMaintenance);

        try {
            $equipmentMaintenance->delete();

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('EquipmentMaintenance destroy failed', ['id' => $equipmentMaintenance->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir manutenção.', 500);
        }
    }
}
