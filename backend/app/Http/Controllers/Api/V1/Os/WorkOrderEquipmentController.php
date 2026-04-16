<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\AttachWorkOrderEquipmentRequest;
use App\Models\Equipment;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderEquipmentController extends Controller
{
    use ResolvesCurrentTenant;

    public function attachEquipment(AttachWorkOrderEquipmentRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        $validated = $request->validated();

        if ($workOrder->equipmentsList()->where('equipment_id', $validated['equipment_id'])->exists()) {
            return ApiResponse::message('Equipamento já vinculado a esta OS', 422);
        }

        try {
            DB::beginTransaction();
            $workOrder->equipmentsList()->attach($validated['equipment_id']);
            DB::commit();

            return ApiResponse::data($workOrder->fresh()->load('equipmentsList:id,type,brand,model,serial_number')->equipmentsList, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder attachEquipment failed', ['wo_id' => $workOrder->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao vincular equipamento', 500);
        }
    }

    public function detachEquipment(WorkOrder $workOrder, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $workOrder);
        if ($error = $this->ensureTenantOwnership($workOrder, 'OS')) {
            return $error;
        }

        if ($equipment->tenant_id !== $workOrder->tenant_id) {
            return ApiResponse::message('Equipamento não pertence a este tenant', 403);
        }

        try {
            DB::beginTransaction();
            $workOrder->equipmentsList()->detach($equipment->id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('WorkOrder detachEquipment failed', ['wo_id' => $workOrder->id, 'equipment_id' => $equipment->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao desvincular equipamento', 500);
        }

        return ApiResponse::noContent();
    }
}
