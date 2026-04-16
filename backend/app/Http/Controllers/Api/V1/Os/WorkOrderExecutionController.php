<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Events\TechnicianLocationUpdated;
use App\Events\WorkOrderCompleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Os\CloseWithoutReturnRequest;
use App\Http\Requests\Os\FinalizeWorkOrderRequest;
use App\Http\Requests\Os\PauseDisplacementRequest;
use App\Http\Requests\Os\PauseServiceRequest;
use App\Http\Requests\Os\StartReturnRequest;
use App\Http\Requests\Os\TimelineWorkOrderRequest;
use App\Http\Requests\Os\WorkOrderExecutionRequest;
use App\Http\Requests\Os\WorkOrderLocationRequest;
use App\Models\AuditLog;
use App\Models\CustomerLocation;
use App\Models\Role;
use App\Models\WorkOrder;
use App\Models\WorkOrderDisplacementLocation;
use App\Models\WorkOrderDisplacementStop;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderStatusHistory;
use App\Modules\OrdemServico\DTO\OrdemServicoFinalizadaPayload;
use App\Modules\OrdemServico\Events\OrdemServicoFinalizadaEvent;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderExecutionController extends Controller
{
    use ResolvesCurrentTenant;

    protected function getEventTimestamp(Request $request, ?array $validated = null): Carbon
    {
        $dateStr = $validated['recorded_at'] ?? $request->input('recorded_at');
        if (! empty($dateStr)) {
            try {
                return Carbon::parse($dateStr);
            } catch (\Exception $e) {
                return now();
            }
        }

        return now();
    }

    // ── Deslocamento ──

    public function startDisplacement(WorkOrderLocationRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! in_array($workOrder->status, [WorkOrder::STATUS_OPEN, WorkOrder::STATUS_AWAITING_DISPATCH], true)) {
            return ApiResponse::message('OS precisa estar aberta ou aguardando despacho.', 422);
        }

        if ($workOrder->displacement_started_at) {
            return ApiResponse::message('Deslocamento já iniciado.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);
            $this->transitionStatus(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_IN_DISPLACEMENT,
                [
                    'displacement_started_at' => $now,
                ],
                'Deslocamento iniciado'
            );

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_DISPLACEMENT_STARTED, $request->user()->id, $validated);

            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                WorkOrderDisplacementLocation::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'user_id' => $request->user()->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                ]);
                $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);
            }

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: deslocamento iniciado", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'displacement_started_at' => $workOrder->displacement_started_at->toIso8601String(),
            ], 200, ['message' => 'Deslocamento iniciado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO start displacement failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao iniciar deslocamento.', 500);
        }
    }

    public function pauseDisplacement(PauseDisplacementRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_IN_DISPLACEMENT) {
            return ApiResponse::message('OS não está em deslocamento.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->transitionStatus(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_DISPLACEMENT_PAUSED,
                [],
                "Deslocamento pausado: {$validated['reason']}"
            );

            $stop = WorkOrderDisplacementStop::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'type' => $validated['stop_type'] ?? 'other',
                'started_at' => $this->getEventTimestamp($request, $validated ?? null),
                'notes' => $validated['reason'],
                'location_lat' => $validated['latitude'] ?? null,
                'location_lng' => $validated['longitude'] ?? null,
            ]);

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_DISPLACEMENT_PAUSED, $request->user()->id, $validated, [
                'reason' => $validated['reason'],
                'stop_id' => $stop->id,
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: deslocamento pausado — {$validated['reason']}", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'stop_id' => $stop->id,
            ], 200, ['message' => 'Deslocamento pausado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO pause displacement failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao pausar deslocamento.', 500);
        }
    }

    public function resumeDisplacement(WorkOrderExecutionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_DISPLACEMENT_PAUSED) {
            return ApiResponse::message('Deslocamento não está pausado.', 422);
        }

        DB::beginTransaction();

        try {
            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_IN_DISPLACEMENT, [], 'Deslocamento retomado');

            $openStop = $workOrder->displacementStops()->whereNull('ended_at')->latest('started_at')->first();
            if ($openStop) {
                $openStop->update(['ended_at' => $this->getEventTimestamp($request)]);
            }

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_DISPLACEMENT_RESUMED, $request->user()->id);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: deslocamento retomado", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
            ], 200, ['message' => 'Deslocamento retomado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO resume displacement failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao retomar deslocamento.', 500);
        }
    }

    // ── Chegada no Cliente ──

    public function arrive(WorkOrderLocationRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! in_array($workOrder->status, [WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_DISPLACEMENT_PAUSED])) {
            return ApiResponse::message('OS não está em deslocamento.', 422);
        }

        if ($workOrder->displacement_arrived_at) {
            return ApiResponse::message('Chegada já registrada.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);

            $openStop = $workOrder->displacementStops()->whereNull('ended_at')->latest('started_at')->first();
            if ($openStop) {
                $openStop->update(['ended_at' => $now]);
            }

            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_AT_CLIENT, [
                'displacement_arrived_at' => $now,
                'arrival_latitude' => $validated['latitude'] ?? null,
                'arrival_longitude' => $validated['longitude'] ?? null,
            ], 'Chegada no cliente');
            $this->recalculateDisplacementDuration($workOrder);

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_ARRIVED_AT_CLIENT, $request->user()->id, $validated);

            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                WorkOrderDisplacementLocation::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'user_id' => $request->user()->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                ]);
                $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);
                $this->syncGpsToCustomer($workOrder, $validated['latitude'], $validated['longitude'], $request->user()->id);
            }

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: chegou no cliente", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'displacement_arrived_at' => $workOrder->fresh()->displacement_arrived_at->toIso8601String(),
                'displacement_duration_minutes' => $workOrder->fresh()->displacement_duration_minutes,
            ], 200, ['message' => 'Chegada registrada.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO arrive failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao registrar chegada.', 500);
        }
    }

    // ── Serviço ──

    public function startService(WorkOrderExecutionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_AT_CLIENT) {
            return ApiResponse::message('OS precisa estar no cliente para iniciar serviço.', 422);
        }

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request);

            $waitTime = null;
            if ($workOrder->displacement_arrived_at) {
                $waitTime = (int) Carbon::parse($workOrder->displacement_arrived_at)->diffInMinutes($now);
            }

            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_IN_SERVICE, [
                'service_started_at' => $now,
                'started_at' => $workOrder->started_at ?? $now,
                'wait_time_minutes' => $waitTime,
            ], 'Serviço iniciado');

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_SERVICE_STARTED, $request->user()->id, [], [
                'wait_time_minutes' => $waitTime,
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: serviço iniciado (espera: {$waitTime}min)", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'service_started_at' => $workOrder->service_started_at->toIso8601String(),
                'wait_time_minutes' => $waitTime,
            ], 200, ['message' => 'Serviço iniciado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO start service failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao iniciar serviço.', 500);
        }
    }

    public function pauseService(PauseServiceRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_IN_SERVICE) {
            return ApiResponse::message('Serviço não está em andamento.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->transitionStatus(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_SERVICE_PAUSED,
                [],
                "Serviço pausado: {$validated['reason']}"
            );

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_SERVICE_PAUSED, $request->user()->id, [], [
                'reason' => $validated['reason'],
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: serviço pausado — {$validated['reason']}", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
            ], 200, ['message' => 'Serviço pausado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO pause service failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao pausar serviço.', 500);
        }
    }

    public function resumeService(WorkOrderExecutionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_SERVICE_PAUSED) {
            return ApiResponse::message('Serviço não está pausado.', 422);
        }

        DB::beginTransaction();

        try {
            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_IN_SERVICE, [], 'Serviço retomado');

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_SERVICE_RESUMED, $request->user()->id);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: serviço retomado", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
            ], 200, ['message' => 'Serviço retomado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO resume service failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao retomar serviço.', 500);
        }
    }

    // ── Finalização do Serviço (agora entra em "Aguardando Retorno") ──

    public function finalize(FinalizeWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! in_array($workOrder->status, [WorkOrder::STATUS_IN_SERVICE, WorkOrder::STATUS_SERVICE_PAUSED, WorkOrder::STATUS_IN_PROGRESS])) {
            return ApiResponse::message('OS precisa estar em serviço para finalizar.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);
            $times = $this->calculateServiceTimes($workOrder, $now);

            $updateData = [
                'service_duration_minutes' => $times['service_duration_minutes'],
            ];

            if (! empty($validated['technical_report'])) {
                $updateData['technical_report'] = $validated['technical_report'];
            }
            if (! empty($validated['resolution_notes'])) {
                $existing = $workOrder->internal_notes;
                $updateData['internal_notes'] = trim(($existing ? $existing."\n" : '').$validated['resolution_notes']);
            }

            $this->transitionStatus(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_AWAITING_RETURN,
                $updateData,
                'Serviço finalizado, aguardando retorno'
            );

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_SERVICE_COMPLETED, $request->user()->id, [], [
                'displacement_minutes' => $workOrder->displacement_duration_minutes ?? 0,
                'wait_time_minutes' => $workOrder->wait_time_minutes ?? 0,
                'service_duration_minutes' => $times['service_duration_minutes'],
                'service_pause_minutes' => $times['service_pause_minutes'],
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: serviço finalizado, aguardando retorno", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'times' => $times,
            ], 200, ['message' => 'Serviço finalizado. Inicie o retorno ou encerre a OS.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO finalize failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao finalizar serviço.', 500);
        }
    }

    // ── Retorno (Volta) ──

    public function startReturn(StartReturnRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_AWAITING_RETURN) {
            return ApiResponse::message('OS precisa estar aguardando retorno.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);
            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_IN_RETURN, [
                'return_started_at' => $now,
                'return_destination' => $validated['destination'],
            ], "Retorno iniciado para {$validated['destination']}");

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_RETURN_STARTED, $request->user()->id, $validated, [
                'destination' => $validated['destination'],
            ]);

            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                WorkOrderDisplacementLocation::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'user_id' => $request->user()->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                ]);
                $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);
            }

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: retorno iniciado ({$validated['destination']})", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'return_started_at' => $workOrder->return_started_at->toIso8601String(),
            ], 200, ['message' => 'Retorno iniciado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO start return failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao iniciar retorno.', 500);
        }
    }

    public function pauseReturn(PauseDisplacementRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_IN_RETURN) {
            return ApiResponse::message('OS não está em retorno.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $this->transitionStatus(
                $workOrder,
                $request->user()->id,
                WorkOrder::STATUS_RETURN_PAUSED,
                [],
                "Retorno pausado: {$validated['reason']}"
            );

            $stop = WorkOrderDisplacementStop::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'type' => $validated['stop_type'] ?? 'other',
                'started_at' => $this->getEventTimestamp($request, $validated ?? null),
                'notes' => '[RETORNO] '.$validated['reason'],
                'location_lat' => $validated['latitude'] ?? null,
                'location_lng' => $validated['longitude'] ?? null,
            ]);

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_RETURN_PAUSED, $request->user()->id, $validated, [
                'reason' => $validated['reason'],
                'stop_id' => $stop->id,
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: retorno pausado — {$validated['reason']}", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
                'stop_id' => $stop->id,
            ], 200, ['message' => 'Retorno pausado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO pause return failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao pausar retorno.', 500);
        }
    }

    public function resumeReturn(WorkOrderExecutionRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_RETURN_PAUSED) {
            return ApiResponse::message('Retorno não está pausado.', 422);
        }

        DB::beginTransaction();

        try {
            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_IN_RETURN, [], 'Retorno retomado');

            $openStop = $workOrder->displacementStops()
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first();
            if ($openStop) {
                $openStop->update(['ended_at' => $this->getEventTimestamp($request)]);
            }

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_RETURN_RESUMED, $request->user()->id);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: retorno retomado", $workOrder);

            DB::commit();

            return ApiResponse::data([
                'status' => $workOrder->status,
            ], 200, ['message' => 'Retorno retomado.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO resume return failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao retomar retorno.', 500);
        }
    }

    public function arriveReturn(WorkOrderLocationRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if (! in_array($workOrder->status, [WorkOrder::STATUS_IN_RETURN, WorkOrder::STATUS_RETURN_PAUSED])) {
            return ApiResponse::message('OS não está em retorno.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);

            $openStop = $workOrder->displacementStops()->whereNull('ended_at')->latest('started_at')->first();
            if ($openStop) {
                $openStop->update(['ended_at' => $now]);
            }

            $returnDuration = $this->calculateReturnDuration($workOrder, $now);
            $totalTimes = $this->calculateAllTimes($workOrder, $now, $returnDuration);

            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_COMPLETED, [
                'completed_at' => $now,
                'return_arrived_at' => $now,
                'return_duration_minutes' => $returnDuration,
                'total_duration_minutes' => $totalTimes['total_duration_minutes'],
            ], 'Retorno concluído, OS finalizada');

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_RETURN_ARRIVED, $request->user()->id, $validated, [
                'return_duration_minutes' => $returnDuration,
                'displacement_ida_minutes' => $totalTimes['displacement_ida_minutes'],
                'wait_time_minutes' => $totalTimes['wait_time_minutes'],
                'service_duration_minutes' => $totalTimes['service_duration_minutes'],
                'return_duration_minutes' => $returnDuration,
                'total_duration_minutes' => $totalTimes['total_duration_minutes'],
            ]);

            if (! empty($validated['latitude']) && ! empty($validated['longitude'])) {
                WorkOrderDisplacementLocation::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'user_id' => $request->user()->id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'recorded_at' => $now,
                ]);
                $this->updateUserLocation($request->user(), $validated['latitude'], $validated['longitude']);
            }

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: retorno concluído, OS finalizada", $workOrder);

            DB::commit();

            $this->dispatchCompletionEvents($workOrder, $request->user());

            return ApiResponse::data([
                'status' => $workOrder->status,
                'times' => $totalTimes,
            ], 200, ['message' => 'Retorno concluído. OS finalizada.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO arrive return failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao registrar chegada do retorno.', 500);
        }
    }

    public function closeWithoutReturn(CloseWithoutReturnRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->authorizeTechnician($request, $workOrder);

        if ($workOrder->status !== WorkOrder::STATUS_AWAITING_RETURN) {
            return ApiResponse::message('OS precisa estar aguardando retorno.', 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $now = $this->getEventTimestamp($request, $validated ?? null);
            $totalTimes = $this->calculateAllTimes($workOrder, $now, 0);

            $this->transitionStatus($workOrder, $request->user()->id, WorkOrder::STATUS_COMPLETED, [
                'completed_at' => $now,
                'total_duration_minutes' => $totalTimes['total_duration_minutes'],
            ], 'OS encerrada sem retorno');

            $this->recordEvent($workOrder, WorkOrderEvent::TYPE_CLOSED_NO_RETURN, $request->user()->id, [], [
                'reason' => $validated['reason'] ?? 'Seguiu para próximo atendimento',
                'displacement_ida_minutes' => $totalTimes['displacement_ida_minutes'],
                'wait_time_minutes' => $totalTimes['wait_time_minutes'],
                'service_duration_minutes' => $totalTimes['service_duration_minutes'],
                'total_duration_minutes' => $totalTimes['total_duration_minutes'],
            ]);

            AuditLog::log('status_changed', "OS {$workOrder->business_number}: encerrada sem retorno", $workOrder);

            DB::commit();

            $this->dispatchCompletionEvents($workOrder, $request->user());

            return ApiResponse::data([
                'status' => $workOrder->status,
                'times' => $totalTimes,
            ], 200, ['message' => 'OS finalizada (sem retorno).']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WO close without return failed', ['error' => $e->getMessage(), 'wo' => $workOrder->id]);

            return ApiResponse::message('Erro ao encerrar OS.', 500);
        }
    }

    // ── Timeline ──

    public function timeline(TimelineWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureTenantScopeOrFail($request, $workOrder);

        /** @var Collection<int, WorkOrderEvent> $eventModels */
        $eventModels = $workOrder->events()
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        $events = [];
        foreach ($eventModels as $e) {
            $events[] = [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'event_label' => WorkOrderEvent::TYPE_LABELS[$e->event_type] ?? $e->event_type,
                'user' => $e->user ? ['id' => $e->user->id, 'name' => $e->user->name] : null,
                'latitude' => $e->latitude,
                'longitude' => $e->longitude,
                'metadata' => $e->metadata,
                'created_at' => $e->created_at->toIso8601String(),
            ];
        }

        return ApiResponse::data($events);
    }

    // ── Helpers Privados ──

    protected function authorizeTechnician(Request $request, WorkOrder $workOrder): void
    {
        $this->ensureTenantScopeOrFail($request, $workOrder);

        $user = $request->user();

        if (! $user->can('os.work_order.change_status')) {
            throw new HttpResponseException(
                ApiResponse::message('Voce nao tem permissao para executar o fluxo desta OS.', 403)
            );
        }

        if ($this->isPrivilegedFieldOperator($user)) {
            return;
        }

        if (! $workOrder->isTechnicianAuthorized($user->id)) {
            throw new HttpResponseException(
                ApiResponse::message('Você não está autorizado a gerenciar a execução desta OS.', 403)
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

    protected function transitionStatus(
        WorkOrder $workOrder,
        ?int $userId,
        string $newStatus,
        array $attributes = [],
        ?string $notes = null
    ): void {
        $fromStatus = $workOrder->status;

        $workOrder->update([
            ...$attributes,
            'status' => $newStatus,
        ]);

        if ($fromStatus === $newStatus) {
            return;
        }

        WorkOrderStatusHistory::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'user_id' => $userId,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'notes' => $notes,
        ]);
    }

    protected function recordEvent(
        WorkOrder $workOrder,
        string $type,
        ?int $userId,
        array $gps = [],
        array $metadata = []
    ): WorkOrderEvent {
        return WorkOrderEvent::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'event_type' => $type,
            'user_id' => $userId,
            'latitude' => $gps['latitude'] ?? null,
            'longitude' => $gps['longitude'] ?? null,
            'metadata' => ! empty($metadata) ? $metadata : null,
        ]);
    }

    protected function dispatchCompletionEvents(WorkOrder $workOrder, $user): void
    {
        try {
            $from = WorkOrder::STATUS_AWAITING_RETURN;
            WorkOrderCompleted::dispatch($workOrder, $user, $from);
            OrdemServicoFinalizadaEvent::dispatch(
                OrdemServicoFinalizadaPayload::fromWorkOrder($workOrder, $user)
            );
        } catch (\Throwable $e) {
            Log::warning('WO completion event dispatch failed', ['wo' => $workOrder->id, 'error' => $e->getMessage()]);
        }
    }

    protected function syncGpsToCustomer(WorkOrder $workOrder, float $lat, float $lng, int $userId): void
    {
        $customer = $workOrder->customer;
        if (! $customer) {
            return;
        }

        CustomerLocation::create([
            'customer_id' => $customer->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'source' => 'work_order_arrival',
            'source_id' => $workOrder->id,
            'label' => "Coletado em atendimento OS {$workOrder->business_number}",
            'collected_by' => $userId,
        ]);

        if (! $customer->latitude || ! $customer->longitude) {
            $customer->update([
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }
    }

    protected function recalculateDisplacementDuration(WorkOrder $workOrder): void
    {
        if (! $workOrder->displacement_started_at || ! $workOrder->displacement_arrived_at) {
            return;
        }

        $start = Carbon::parse($workOrder->displacement_started_at);
        $arrived = Carbon::parse($workOrder->displacement_arrived_at);
        $grossMinutes = (int) $start->diffInMinutes($arrived);

        $stopMinutes = $workOrder->displacementStops()
            ->whereNotNull('ended_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes ?? 0);

        $workOrder->update([
            'displacement_duration_minutes' => max(0, $grossMinutes - $stopMinutes),
        ]);
    }

    protected function calculateServicePauseMinutes(WorkOrder $workOrder): int
    {
        $pauseEvents = $workOrder->events()
            ->whereIn('event_type', [WorkOrderEvent::TYPE_SERVICE_PAUSED, WorkOrderEvent::TYPE_SERVICE_RESUMED])
            ->orderBy('created_at')
            ->get();

        $totalPause = 0;
        $lastPause = null;

        foreach ($pauseEvents as $event) {
            if ($event->event_type === WorkOrderEvent::TYPE_SERVICE_PAUSED) {
                $lastPause = Carbon::parse($event->created_at);
            } elseif ($event->event_type === WorkOrderEvent::TYPE_SERVICE_RESUMED && $lastPause) {
                $totalPause += (int) $lastPause->diffInMinutes(Carbon::parse($event->created_at));
                $lastPause = null;
            }
        }

        if ($lastPause) {
            $totalPause += (int) $lastPause->diffInMinutes(now());
        }

        return $totalPause;
    }

    protected function calculateServiceTimes(WorkOrder $workOrder, Carbon $serviceEndedAt): array
    {
        $servicePauseMinutes = $this->calculateServicePauseMinutes($workOrder);
        $serviceGrossMinutes = 0;
        if ($workOrder->service_started_at) {
            $serviceGrossMinutes = (int) Carbon::parse($workOrder->service_started_at)->diffInMinutes($serviceEndedAt);
        }
        $serviceNetMinutes = max(0, $serviceGrossMinutes - $servicePauseMinutes);

        return [
            'service_pause_minutes' => $servicePauseMinutes,
            'service_duration_minutes' => $serviceNetMinutes,
        ];
    }

    protected function calculateReturnDuration(WorkOrder $workOrder, Carbon $arrivedAt): int
    {
        if (! $workOrder->return_started_at) {
            return 0;
        }

        $start = Carbon::parse($workOrder->return_started_at);
        $grossMinutes = (int) $start->diffInMinutes($arrivedAt);

        $returnStopMinutes = $workOrder->displacementStops()
            ->where('notes', 'like', '[RETORNO]%')
            ->whereNotNull('ended_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes ?? 0);

        return max(0, $grossMinutes - $returnStopMinutes);
    }

    protected function calculateAllTimes(WorkOrder $workOrder, Carbon $completedAt, int $returnMinutes = 0): array
    {
        $displacementIdaMinutes = $workOrder->displacement_duration_minutes ?? 0;
        $waitTimeMinutes = $workOrder->wait_time_minutes ?? 0;
        $serviceMinutes = $workOrder->service_duration_minutes ?? 0;

        $totalMinutes = 0;
        if ($workOrder->displacement_started_at) {
            $totalMinutes = (int) Carbon::parse($workOrder->displacement_started_at)->diffInMinutes($completedAt);
        }

        return [
            'displacement_ida_minutes' => $displacementIdaMinutes,
            'wait_time_minutes' => $waitTimeMinutes,
            'service_duration_minutes' => $serviceMinutes,
            'return_duration_minutes' => $returnMinutes,
            'total_duration_minutes' => $totalMinutes,
        ];
    }
}
