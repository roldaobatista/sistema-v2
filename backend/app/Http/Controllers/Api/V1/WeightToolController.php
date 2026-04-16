<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\AssignWeightRequest;
use App\Http\Requests\Features\StoreToolCalibrationRequest;
use App\Http\Requests\Features\UpdateToolCalibrationRequest;
use App\Models\StandardWeight;
use App\Models\ToolCalibration;
use App\Models\ToolInventory;
use App\Models\WeightAssignment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeightToolController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ── Atribuição de Pesos Padrão ─────────────────────────────────

    public function indexWeightAssignments(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            WeightAssignment::where('tenant_id', $this->tenantId($request))
                ->with(['weight:id,code,nominal_value,unit', 'user:id,name', 'vehicle:id,plate,model'])
                ->orderByDesc('assigned_at')
                ->paginate(min((int) $request->input('per_page', 25), 100))
        );
    }

    public function assignWeight(AssignWeightRequest $request): JsonResponse
    {
        $data = $request->validated();
        WeightAssignment::where('standard_weight_id', $data['standard_weight_id'])
            ->whereNull('returned_at')
            ->update(['returned_at' => now()]);

        $assignment = WeightAssignment::create([
            'tenant_id' => $this->tenantId($request),
            'standard_weight_id' => $data['standard_weight_id'],
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'assigned_to_vehicle_id' => $data['assigned_to_vehicle_id'] ?? null,
            'assignment_type' => $data['assignment_type'] ?? 'field',
            'assigned_at' => now(),
            'assigned_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        StandardWeight::find($data['standard_weight_id'])?->update([
            'assigned_to_user_id' => $data['assigned_to_user_id'],
            'assigned_to_vehicle_id' => $data['assigned_to_vehicle_id'],
        ]);

        return ApiResponse::data($assignment->load(['weight', 'user', 'vehicle']), 201);
    }

    public function returnWeight(WeightAssignment $assignment): JsonResponse
    {
        $assignment->update(['returned_at' => now()]);
        $assignment->weight?->update(['assigned_to_user_id' => null, 'assigned_to_vehicle_id' => null]);

        return ApiResponse::message('Peso devolvido.');
    }

    // ── Calibração de Ferramentas ──────────────────────────────────

    public function indexToolCalibrations(Request $request): JsonResponse
    {
        $paginator = ToolCalibration::where('tenant_id', $this->tenantId($request))
            ->with('tool:id,name,serial_number')
            ->when($request->filled('tool_id'), fn ($query) => $query->where('tool_inventory_id', (int) $request->input('tool_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('result', (string) $request->input('status')))
            ->orderByDesc('calibration_date')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        $paginator->through(
            function (ToolCalibration $calibration): array {
                $payload = $calibration->toArray();
                $tool = $calibration->tool;
                $payload['tool_name'] = $tool instanceof ToolInventory ? $tool->name : null;

                return $payload;
            }
        );

        return ApiResponse::paginated($paginator);
    }

    public function storeToolCalibration(StoreToolCalibrationRequest $request): JsonResponse
    {
        $data = $this->normalizeCalibrationPayload($request->validated());
        $data['tenant_id'] = $this->tenantId($request);

        return ApiResponse::data(ToolCalibration::create($data), 201);
    }

    public function updateToolCalibration(UpdateToolCalibrationRequest $request, ToolCalibration $calibration): JsonResponse
    {
        if ((int) $calibration->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Calibração não encontrada.', 404);
        }

        $data = $this->normalizeCalibrationPayload($request->validated());
        $calibration->update(array_filter($data, fn ($v) => $v !== null));

        return ApiResponse::data($calibration);
    }

    public function destroyToolCalibration(Request $request, ToolCalibration $calibration): JsonResponse
    {
        if ((int) $calibration->tenant_id !== $this->tenantId($request)) {
            return ApiResponse::message('Calibração não encontrada.', 404);
        }

        $calibration->delete();

        return ApiResponse::noContent();
    }

    public function expiringToolCalibrations(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        return ApiResponse::data(
            ToolCalibration::where('tenant_id', $this->tenantId($request))
                ->expiring($days)
                ->with('tool:id,name,serial_number')
                ->orderBy('next_due_date')
                ->get()
        );
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCalibrationPayload(array $payload): array
    {
        if (array_key_exists('next_calibration_date', $payload) && ! array_key_exists('next_due_date', $payload)) {
            $payload['next_due_date'] = $payload['next_calibration_date'];
        }

        if (array_key_exists('performed_by', $payload) && ! array_key_exists('laboratory', $payload)) {
            $payload['laboratory'] = $payload['performed_by'];
        }

        if (array_key_exists('status', $payload) && ! array_key_exists('result', $payload)) {
            $payload['result'] = $payload['status'];
        }

        unset($payload['next_calibration_date'], $payload['performed_by'], $payload['status']);

        return $payload;
    }
}
