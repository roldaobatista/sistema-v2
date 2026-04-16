<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Events\TechnicianLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Os\AddDisplacementStopRequest;
use App\Http\Requests\Os\ArriveDisplacementRequest;
use App\Http\Requests\Os\RecordDisplacementLocationRequest;
use App\Http\Requests\Os\StartDisplacementRequest;
use App\Http\Requests\Os\WorkOrderExecutionRequest;
use App\Models\Role;
use App\Models\WorkOrder;
use App\Models\WorkOrderDisplacementLocation;
use App\Models\WorkOrderDisplacementStop;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderDisplacementController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureTenantScopeOrFail($request, $workOrder);
        $this->authorize('view', $workOrder);

        $workOrder->load(['displacementStops', 'displacementLocations' => fn ($q) => $q->orderBy('recorded_at')->limit(100)]);

        return ApiResponse::data([
            'displacement_started_at' => $workOrder->displacement_started_at?->toIso8601String(),
            'displacement_arrived_at' => $workOrder->displacement_arrived_at?->toIso8601String(),
            'displacement_duration_minutes' => $workOrder->displacement_duration_minutes,
            'displacement_status' => $this->displacementStatus($workOrder),
            'stops' => $workOrder->displacementStops->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->type,
                'type_label' => WorkOrderDisplacementStop::TYPES[$s->type] ?? $s->type,
                'started_at' => $s->started_at->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'duration_minutes' => $s->duration_minutes,
                'notes' => $s->notes,
            ]),
            'locations_count' => $workOrder->displacementLocations()->count(),
        ]);
    }

    public function start(StartDisplacementRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->displacement_started_at) {
            return ApiResponse::message('Deslocamento já iniciado.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $workOrder->update([
                'displacement_started_at' => now(),
                'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
            ]);

            WorkOrderDisplacementLocation::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => $request->user()->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'recorded_at' => now(),
            ]);

            $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);

            DB::commit();

            return ApiResponse::data([
                'displacement_started_at' => $workOrder->displacement_started_at->toIso8601String(),
            ], 201, ['message' => 'Deslocamento iniciado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO displacement start failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao iniciar deslocamento.', 500);
        }
    }

    public function arrive(ArriveDisplacementRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! $workOrder->displacement_started_at) {
            return ApiResponse::message('Deslocamento não foi iniciado.', 422);
        }
        if ($workOrder->displacement_arrived_at) {
            return ApiResponse::message('Chegada já registrada.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $workOrder->update([
                'displacement_arrived_at' => now(),
                'status' => WorkOrder::STATUS_AT_CLIENT,
                'arrival_latitude' => $validated['latitude'] ?? null,
                'arrival_longitude' => $validated['longitude'] ?? null,
            ]);

            if (isset($validated['latitude']) && isset($validated['longitude'])) {
                WorkOrderDisplacementLocation::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'user_id' => $request->user()->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => now(),
                ]);
                $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);
            }

            $this->recalculateDisplacementDuration($workOrder);

            DB::commit();

            return ApiResponse::data([
                'displacement_arrived_at' => $workOrder->fresh()->displacement_arrived_at->toIso8601String(),
                'displacement_duration_minutes' => $workOrder->fresh()->displacement_duration_minutes,
            ], 200, ['message' => 'Chegada registrada.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO displacement arrive failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao registrar chegada.', 500);
        }
    }

    public function recordLocation(RecordDisplacementLocationRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! $workOrder->displacement_started_at || $workOrder->displacement_arrived_at) {
            return ApiResponse::message('Deslocamento não está em andamento.', 422);
        }

        $validated = $request->validated();

        WorkOrderDisplacementLocation::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id' => $request->user()->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => now(),
        ]);

        $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);

        return ApiResponse::data([
            'recorded_at' => now()->toIso8601String(),
        ], 201, ['message' => 'Localização registrada.']);
    }

    public function addStop(AddDisplacementStopRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! $workOrder->displacement_started_at || $workOrder->displacement_arrived_at) {
            return ApiResponse::message('Deslocamento não está em andamento.', 422);
        }

        $validated = $request->validated();

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'type' => $validated['type'],
            'started_at' => now(),
            'notes' => $validated['notes'] ?? null,
            'location_lat' => $validated['latitude'] ?? null,
            'location_lng' => $validated['longitude'] ?? null,
        ]);

        return ApiResponse::data([
            'stop' => [
                'id' => $stop->id,
                'type' => $stop->type,
                'type_label' => WorkOrderDisplacementStop::TYPES[$stop->type] ?? $stop->type,
                'started_at' => $stop->started_at->toIso8601String(),
            ],
        ], 201, ['message' => 'Parada registrada.']);
    }

    public function endStop(WorkOrderExecutionRequest $request, WorkOrder $workOrder, WorkOrderDisplacementStop $stop): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($stop->work_order_id !== (int) $workOrder->id) {
            return ApiResponse::message('Parada não encontrada para esta OS.', 404);
        }
        if ($stop->ended_at) {
            return ApiResponse::message('Parada já finalizada.', 422);
        }

        $stop->update(['ended_at' => now()]);

        if ($workOrder->displacement_arrived_at) {
            $this->recalculateDisplacementDuration($workOrder);
        }

        return ApiResponse::data([
            'stop' => [
                'id' => $stop->id,
                'ended_at' => $stop->fresh()->ended_at->toIso8601String(),
                'duration_minutes' => $stop->fresh()->duration_minutes,
            ],
        ], 200, ['message' => 'Parada finalizada.']);
    }

    protected function authorizeTechnician(Request $request, WorkOrder $workOrder): void
    {
        $this->ensureTenantScopeOrFail($request, $workOrder);

        $user = $request->user();

        if (! $user->can('os.work_order.change_status')) {
            throw new HttpResponseException(
                ApiResponse::message('Voce nao tem permissao para gerenciar o deslocamento desta OS.', 403)
            );
        }

        if ($this->isPrivilegedFieldOperator($user)) {
            return;
        }

        if (! $workOrder->isTechnicianAuthorized($user->id)) {
            throw new HttpResponseException(
                ApiResponse::message('Você não está autorizado a gerenciar o deslocamento desta OS.', 403)
            );
        }
    }

    protected function ensureTenantScopeOrFail(Request $request, WorkOrder $workOrder): void
    {
        if ((int) $workOrder->tenant_id !== $this->tenantId()) {
            throw new HttpResponseException(
                ApiResponse::message('Acesso negado: OS não pertence ao tenant atual.', 403)
            );
        }
    }

    protected function isPrivilegedFieldOperator($user): bool
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

    protected function updateUserLocation($user, float $lat, float $lng): void
    {
        $user->forceFill([
            'location_lat' => $lat,
            'location_lng' => $lng,
            'location_updated_at' => now(),
        ])->save();
        broadcast(new TechnicianLocationUpdated($user));
    }

    protected function recalculateDisplacementDuration(WorkOrder $workOrder): void
    {
        $start = Carbon::parse($workOrder->displacement_started_at);
        $arrived = Carbon::parse($workOrder->displacement_arrived_at);
        $grossMinutes = (int) $start->diffInMinutes($arrived);

        $stopMinutes = $workOrder->displacementStops()
            ->whereNotNull('ended_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes ?? 0);

        $effectiveMinutes = max(0, $grossMinutes - $stopMinutes);

        $workOrder->update(['displacement_duration_minutes' => $effectiveMinutes]);
    }

    protected function displacementStatus(WorkOrder $workOrder): string
    {
        if (! $workOrder->displacement_started_at) {
            return 'not_started';
        }
        if ($workOrder->displacement_arrived_at) {
            return 'arrived';
        }

        return 'in_progress';
    }
}
