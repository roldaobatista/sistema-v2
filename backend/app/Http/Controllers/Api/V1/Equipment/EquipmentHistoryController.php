<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\EquipmentMaintenance;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EquipmentHistoryController extends Controller
{
    private function tenantId(): int
    {
        /** @var User $user */
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function history(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);
        if ((int) $equipment->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $calibrations = $equipment->calibrations()
            ->select('id', 'equipment_id', 'certificate_number', 'calibration_date', 'result', 'created_at')
            ->orderByDesc('calibration_date')
            ->limit(50)
            ->get()
            ->map(static function ($c) {
                assert($c instanceof EquipmentCalibration);

                return [
                    'id' => $c->id,
                    'type' => 'calibration',
                    'title' => "Calibração {$c->certificate_number}",
                    'result' => $c->result,
                    'date' => $c->calibration_date ?? $c->created_at,
                ];
            });

        $workOrders = $equipment->workOrders()
            ->select('id', 'equipment_id', 'number', 'os_number', 'status', 'description', 'created_at', 'completed_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(static function ($wo) {
                assert($wo instanceof WorkOrder);

                return [
                    'id' => $wo->id,
                    'type' => 'work_order',
                    'title' => 'OS '.($wo->os_number ?: $wo->number),
                    'status' => $wo->status,
                    'description' => $wo->description,
                    'date' => $wo->completed_at ?? $wo->created_at,
                ];
            });

        $maintenances = $equipment->maintenances()
            ->select('id', 'equipment_id', 'type', 'description', 'next_maintenance_at', 'created_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(static function ($m) {
                assert($m instanceof EquipmentMaintenance);

                return [
                    'id' => $m->id,
                    'type' => 'maintenance',
                    'title' => "Manutenção ({$m->type})",
                    'description' => $m->description,
                    'date' => $m->next_maintenance_at ?? $m->created_at,
                ];
            });

        $history = $calibrations->concat($workOrders)->concat($maintenances)
            ->sortByDesc('date')
            ->values();

        return ApiResponse::data($history);
    }

    public function workOrders(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);
        if ((int) $equipment->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $workOrders = $equipment->workOrders()
            ->select('id', 'equipment_id', 'number', 'os_number', 'status', 'description', 'created_at', 'completed_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(static function ($wo) {
                assert($wo instanceof WorkOrder);

                return [
                    'id' => $wo->id,
                    'number' => $wo->os_number ?: $wo->number,
                    'status' => $wo->status,
                    'description' => $wo->description,
                    'created_at' => $wo->created_at,
                    'completed_at' => $wo->completed_at,
                ];
            });

        return ApiResponse::data($workOrders);
    }
}
