<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\IndexWorkOrderTimeLogRequest;
use App\Http\Requests\Os\StartWorkOrderTimeLogRequest;
use App\Http\Requests\Os\StopWorkOrderTimeLogRequest;
use App\Http\Resources\WorkOrderTimeLogResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderTimeLogController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexWorkOrderTimeLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workOrder = WorkOrder::where('tenant_id', $this->resolvedTenantId())->findOrFail($validated['work_order_id']);
        $this->authorize('view', $workOrder);

        $logs = WorkOrderTimeLog::where('work_order_id', $validated['work_order_id'])
            ->with('user:id,name')
            ->orderBy('started_at', 'desc')
            ->paginate(max(1, min((int) $request->input('per_page', 25), 100)));

        return ApiResponse::paginated($logs, resourceClass: WorkOrderTimeLogResource::class);
    }

    public function start(StartWorkOrderTimeLogRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workOrder = WorkOrder::where('tenant_id', $this->resolvedTenantId())->findOrFail($validated['work_order_id']);
        $this->authorize('update', $workOrder);

        try {
            DB::beginTransaction();

            $openLogs = WorkOrderTimeLog::where('user_id', $request->user()->id)
                ->where('work_order_id', $validated['work_order_id'])
                ->whereNull('ended_at')
                ->get();

            $now = now();
            foreach ($openLogs as $openLog) {
                $openLog->update([
                    'ended_at' => $now,
                    'duration_seconds' => $openLog->started_at->diffInSeconds($now, true),
                ]);
            }

            $log = WorkOrderTimeLog::create([
                ...$validated,
                'tenant_id' => $this->resolvedTenantId(),
                'user_id' => $request->user()->id,
                'started_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(new WorkOrderTimeLogResource($log), 201, ['message' => 'Timer iniciado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Time log start failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar timer', 500);
        }
    }

    public function stop(StopWorkOrderTimeLogRequest $request, WorkOrderTimeLog $workOrderTimeLog): JsonResponse
    {
        try {
            abort_unless((int) $workOrderTimeLog->tenant_id === (int) $this->resolvedTenantId(), 404);

            if ((int) $workOrderTimeLog->user_id !== (int) $request->user()->id) {
                return ApiResponse::message('Sem permissão para parar o timer de outro usuário', 403);
            }

            if ($workOrderTimeLog->ended_at) {
                return ApiResponse::message('Timer já foi finalizado', 422);
            }

            $validated = $request->validated();

            $workOrderTimeLog->update([
                'ended_at' => now(),
                'duration_seconds' => $workOrderTimeLog->started_at->diffInSeconds(now(), true),
                'latitude' => $validated['latitude'] ?? $workOrderTimeLog->latitude,
                'longitude' => $validated['longitude'] ?? $workOrderTimeLog->longitude,
            ]);

            return ApiResponse::data(new WorkOrderTimeLogResource($workOrderTimeLog->fresh()), 200, ['message' => 'Timer parado']);
        } catch (\Exception $e) {
            Log::error('Time log stop failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao parar timer', 500);
        }
    }
}
