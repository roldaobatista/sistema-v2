<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\CheckinWorkOrderRequest;
use App\Http\Requests\Features\CheckoutWorkOrderRequest;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use App\Models\WorkOrderEvent;
use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderFieldController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function checkinWorkOrder(CheckinWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeWorkOrderFieldFlow($request, $workOrder, 'registrar check-in');

        if ($workOrder->checkin_at) {
            return ApiResponse::message('Check-in já registrado para esta OS.', 422);
        }

        if (in_array($workOrder->status, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_CANCELLED], true)) {
            return ApiResponse::message('Não é possível registrar check-in para uma OS encerrada.', 422);
        }

        DB::transaction(function () use ($request, $workOrder, $data) {
            $now = now();

            $workOrder->update([
                'checkin_at' => $now,
                'checkin_lat' => $data['lat'],
                'checkin_lng' => $data['lng'],
            ]);

            WorkOrderEvent::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'event_type' => WorkOrderEvent::TYPE_CHECKIN_REGISTERED,
                'user_id' => $request->user()?->id,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
            ]);

            WorkOrderChat::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()?->id,
                'message' => 'Check-in geolocalizado registrado na OS.',
                'type' => 'system',
            ]);

            AuditLog::log(
                'updated',
                "OS {$workOrder->business_number}: check-in geolocalizado registrado",
                $workOrder,
                ['checkin_at' => null, 'checkin_lat' => null, 'checkin_lng' => null],
                ['checkin_at' => $now->toIso8601String(), 'checkin_lat' => $data['lat'], 'checkin_lng' => $data['lng']]
            );
        });

        return ApiResponse::data(['checkin_at' => $workOrder->checkin_at], 200, ['message' => 'Check-in registrado.']);
    }

    public function checkoutWorkOrder(CheckoutWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeWorkOrderFieldFlow($request, $workOrder, 'registrar check-out');

        if (! $workOrder->checkin_at) {
            return ApiResponse::message('É necessário registrar check-in antes do check-out.', 422);
        }

        if ($workOrder->checkout_at) {
            return ApiResponse::message('Check-out já registrado para esta OS.', 422);
        }

        if (in_array($workOrder->status, [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_CANCELLED], true)) {
            return ApiResponse::message('Não é possível registrar check-out para uma OS encerrada.', 422);
        }

        $autoKm = null;

        DB::transaction(function () use ($request, $workOrder, $data, &$autoKm) {
            if ($workOrder->checkin_lat && $workOrder->checkin_lng) {
                $autoKm = $this->haversineDistance($workOrder->checkin_lat, $workOrder->checkin_lng, $data['lat'], $data['lng']);
            }

            $now = now();

            $workOrder->update([
                'checkout_at' => $now,
                'checkout_lat' => $data['lat'],
                'checkout_lng' => $data['lng'],
                'auto_km_calculated' => $autoKm,
            ]);

            WorkOrderEvent::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'event_type' => WorkOrderEvent::TYPE_CHECKOUT_REGISTERED,
                'user_id' => $request->user()?->id,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'metadata' => ['auto_km' => $autoKm],
            ]);

            WorkOrderChat::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()?->id,
                'message' => 'Check-out geolocalizado registrado na OS.',
                'type' => 'system',
            ]);

            AuditLog::log(
                'updated',
                "OS {$workOrder->business_number}: check-out geolocalizado registrado",
                $workOrder,
                ['checkout_at' => null, 'checkout_lat' => null, 'checkout_lng' => null, 'auto_km_calculated' => null],
                ['checkout_at' => $now->toIso8601String(), 'checkout_lat' => $data['lat'], 'checkout_lng' => $data['lng'], 'auto_km_calculated' => $autoKm]
            );
        });

        return ApiResponse::data(['auto_km' => $autoKm], 200, ['message' => 'Check-out registrado.']);
    }

    public function equipmentByQr(string $token): JsonResponse
    {
        $equipment = Equipment::where('qr_token', $token)
            ->with(['customer:id,name', 'calibrations' => fn ($q) => $q->latest()->limit(1)])
            ->firstOrFail();

        $lastCal = $equipment->calibrations->first();
        $tenant = Tenant::find($equipment->tenant_id);

        return ApiResponse::data([
            'equipment' => [
                'code' => $equipment->code,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'capacity' => $equipment->capacity,
                'capacity_unit' => $equipment->capacity_unit,
                'resolution' => $equipment->resolution,
                'precision_class' => $equipment->precision_class,
                'location' => $equipment->location,
            ],
            'customer' => $equipment->customer ? ['name' => $equipment->customer->name] : null,
            'tenant' => $tenant ? ['name' => $tenant->name] : null,
            'last_calibration' => $lastCal ? [
                'certificate_number' => $lastCal->certificate_number,
                'calibration_date' => $lastCal->calibration_date?->toDateString(),
                'next_due_date' => $lastCal->next_due_date?->toDateString(),
                'result' => $lastCal->result,
                'laboratory' => $lastCal->laboratory,
            ] : null,
        ]);
    }

    public function generateEquipmentQr(Equipment $equipment): JsonResponse
    {
        if (! $equipment->qr_token) {
            $equipment->update(['qr_token' => Str::random(48)]);
        }

        $publicUrl = config('app.url')."/api/v1/equipment-qr/{$equipment->qr_token}";

        return ApiResponse::data(['qr_token' => $equipment->qr_token, 'public_url' => $publicUrl]);
    }

    public function dashboardNps(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);

        $surveys = SatisfactionSurvey::where('tenant_id', $tid)
            ->whereNotNull('nps_score')
            ->where('created_at', '>=', now()->subMonths(3))
            ->get();

        if ($surveys->isEmpty()) {
            return ApiResponse::data(null);
        }

        $total = $surveys->count();
        $promoters = $surveys->where('nps_score', '>=', 9)->count();
        $detractors = $surveys->where('nps_score', '<=', 6)->count();
        $npsScore = round(($promoters - $detractors) / $total * 100);

        $avgRating = $surveys->avg('service_rating');

        return ApiResponse::data([
            'nps_score' => $npsScore,
            'promoters' => round($promoters / $total * 100),
            'detractors' => round($detractors / $total * 100),
            'avg_rating' => $avgRating,
            'total_responses' => $total,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function ensureWorkOrderTenantScopeOrFail(Request $request, WorkOrder $workOrder): void
    {
        if ((int) $workOrder->tenant_id !== $this->tenantId($request)) {
            throw new HttpResponseException(
                ApiResponse::message('Acesso negado: OS não pertence ao tenant atual.', 403)
            );
        }
    }

    private function authorizeWorkOrderFieldFlow(Request $request, WorkOrder $workOrder, string $actionLabel): void
    {
        $this->ensureWorkOrderTenantScopeOrFail($request, $workOrder);

        $user = $request->user();

        if (! $user->can('os.work_order.change_status')) {
            throw new HttpResponseException(
                ApiResponse::message("Voce nao tem permissao para {$actionLabel} desta OS.", 403)
            );
        }

        if ($this->isPrivilegedWorkOrderFieldOperator($user)) {
            return;
        }

        if (! $workOrder->isTechnicianAuthorized($user->id)) {
            throw new HttpResponseException(
                ApiResponse::message("Voce nao esta autorizado a {$actionLabel} desta OS.", 403)
            );
        }
    }

    private function isPrivilegedWorkOrderFieldOperator(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            Role::SUPER_ADMIN,
            Role::ADMIN,
            Role::GERENTE,
        ]);
    }
}
