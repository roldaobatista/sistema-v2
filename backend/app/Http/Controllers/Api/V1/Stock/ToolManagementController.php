<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreToolCalibrationRequest;
use App\Http\Requests\Stock\StoreToolInventoryRequest;
use App\Http\Requests\Stock\UpdateToolCalibrationRequest;
use App\Http\Requests\Stock\UpdateToolInventoryRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToolManagementController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function inventoryIndex(Request $request): JsonResponse
    {
        $query = DB::table('tool_inventories')
            ->where('tenant_id', $this->tenantId())
            ->when($request->get('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->get('user_id'), fn ($q, $uid) => $q->where('assigned_to', $uid))
            ->when($request->get('status'), fn ($q, $st) => $q->where('status', $st));

        return ApiResponse::paginated($query->orderBy('name')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function inventoryStore(StoreToolInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $data = [
                'name' => $validated['name'],
                'serial_number' => $validated['serial_number'] ?? null,
                'notes' => $validated['description'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'status' => $validated['status'] ?? 'available',
                'value' => $validated['purchase_value'] ?? null,
                'tenant_id' => $this->tenantId(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $id = DB::table('tool_inventories')->insertGetId($data);

            return ApiResponse::data(DB::table('tool_inventories')->find($id), 201);
        } catch (\Throwable $e) {
            Log::error('ToolInventory store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar ferramenta.', 500);
        }
    }

    public function inventoryUpdate(UpdateToolInventoryRequest $request, int $id): JsonResponse
    {
        $tool = DB::table('tool_inventories')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $tool) {
            return ApiResponse::message('Ferramenta não encontrada.', 404);
        }

        $validated = $request->validated();
        $data = collect($validated)->only(['name', 'serial_number', 'assigned_to', 'status'])->toArray();
        if (array_key_exists('description', $validated)) {
            $data['notes'] = $validated['description'];
        }
        $data['updated_at'] = now();
        DB::table('tool_inventories')->where('id', $id)->update($data);

        return ApiResponse::data(DB::table('tool_inventories')->find($id));
    }

    public function inventoryDestroy(int $id): JsonResponse
    {
        $deleted = DB::table('tool_inventories')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->delete();

        return $deleted
            ? ApiResponse::noContent()
            : ApiResponse::message('Ferramenta não encontrada.', 404);
    }

    public function calibrationIndex(Request $request): JsonResponse
    {
        $query = DB::table('tool_calibrations')
            ->leftJoin('tool_inventories', 'tool_calibrations.tool_inventory_id', '=', 'tool_inventories.id')
            ->where('tool_calibrations.tenant_id', $this->tenantId())
            ->select('tool_calibrations.*', 'tool_inventories.name as tool_name')
            ->when($request->get('tool_id'), fn ($q, $tid) => $q->where('tool_inventory_id', $tid))
            ->when($request->get('status'), fn ($q, $st) => $q->where('tool_calibrations.result', $st));

        return ApiResponse::paginated($query->orderByDesc('calibration_date')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function calibrationStore(StoreToolCalibrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $data = [
                'tool_inventory_id' => $validated['tool_inventory_id'],
                'calibration_date' => $validated['calibration_date'],
                'next_due_date' => $validated['next_calibration_date'] ?? null,
                'certificate_number' => $validated['certificate_number'] ?? null,
                'laboratory' => $validated['performed_by'] ?? null,
                'result' => $validated['result'] ?? 'approved',
                'cost' => $validated['cost'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tenant_id' => $this->tenantId(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $id = DB::table('tool_calibrations')->insertGetId($data);

            return ApiResponse::data(DB::table('tool_calibrations')->find($id), 201);
        } catch (\Throwable $e) {
            Log::error('ToolCalibration store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar calibração.', 500);
        }
    }

    public function calibrationUpdate(UpdateToolCalibrationRequest $request, int $id): JsonResponse
    {
        $calibration = DB::table('tool_calibrations')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $calibration) {
            return ApiResponse::message('Calibração não encontrada.', 404);
        }

        $validated = $request->validated();
        $data = [];
        if (array_key_exists('calibration_date', $validated)) {
            $data['calibration_date'] = $validated['calibration_date'];
        }
        if (array_key_exists('next_calibration_date', $validated)) {
            $data['next_due_date'] = $validated['next_calibration_date'];
        }
        if (array_key_exists('certificate_number', $validated)) {
            $data['certificate_number'] = $validated['certificate_number'];
        }
        if (array_key_exists('performed_by', $validated)) {
            $data['laboratory'] = $validated['performed_by'];
        }
        if (array_key_exists('result', $validated)) {
            $data['result'] = $validated['result'];
        }
        if (array_key_exists('status', $validated)) {
            $data['result'] = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $data['notes'] = $validated['notes'];
        }
        $data['updated_at'] = now();
        DB::table('tool_calibrations')->where('id', $id)->update($data);

        return ApiResponse::data(DB::table('tool_calibrations')->find($id));
    }

    public function calibrationDestroy(int $id): JsonResponse
    {
        $deleted = DB::table('tool_calibrations')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->delete();

        return $deleted
            ? ApiResponse::noContent()
            : ApiResponse::message('Calibração não encontrada.', 404);
    }
}
