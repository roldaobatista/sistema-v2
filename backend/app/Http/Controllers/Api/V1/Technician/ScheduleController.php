<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\CheckScheduleConflictsRequest;
use App\Http\Requests\Technician\StoreScheduleRequest;
use App\Http\Requests\Technician\UpdateScheduleRequest;
use App\Models\CrmActivity;
use App\Models\Schedule;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Search\ConflictDetectionService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    private function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->exists();
    }

    private function ensureTenantUser(int $userId, int $tenantId, string $field = 'technician_id'): void
    {
        if (! $this->userBelongsToTenant($userId, $tenantId)) {
            throw ValidationException::withMessages([
                $field => ['Usuário nao pertence ao tenant atual.'],
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Schedule::class);
        $tenantId = $this->tenantId($request);

        $query = Schedule::with([
            'technician:id,name',
            'customer:id,name',
            'workOrder:id,number,os_number,status',
        ])->where('tenant_id', $tenantId);

        if ($techId = $request->get('technician_id')) {
            $query->where('technician_id', $techId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($date = $request->get('date')) {
            $query->whereDate('scheduled_start', $date);
        }

        if ($from = $request->get('from')) {
            $query->where('scheduled_start', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('scheduled_end', '<=', $to);
        }

        $schedules = $query->orderBy('scheduled_start')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        return ApiResponse::paginated($schedules);
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $this->authorize('create', Schedule::class);
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();

        $this->ensureTenantUser((int) $validated['technician_id'], $tenantId);

        if (Schedule::hasConflict($validated['technician_id'], $validated['scheduled_start'], $validated['scheduled_end'], null, $tenantId)) {
            return ApiResponse::message('Conflito de horario: tecnico já possui agendamento neste periodo.', 409);
        }

        try {
            $schedule = DB::transaction(function () use ($validated, $tenantId) {
                return Schedule::create([
                    ...$validated,
                    'tenant_id' => $tenantId,
                    'status' => $validated['status'] ?? Schedule::STATUS_SCHEDULED,
                ]);
            });

            return ApiResponse::data($schedule->load(['technician:id,name', 'customer:id,name', 'workOrder:id,number,os_number']), 201);
        } catch (\Throwable $e) {
            Log::error('Schedule store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar agendamento', 500);
        }
    }

    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorize('view', $schedule);

        return ApiResponse::data($schedule->load([
            'technician:id,name',
            'customer:id,name,phone,email',
            'workOrder:id,number,os_number,status,description',
        ]));
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $this->authorize('update', $schedule);
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();

        if (array_key_exists('technician_id', $validated)) {
            $this->ensureTenantUser((int) $validated['technician_id'], $tenantId);
        }

        $techId = $validated['technician_id'] ?? $schedule->technician_id;
        $start = $validated['scheduled_start'] ?? $schedule->scheduled_start;
        $end = $validated['scheduled_end'] ?? $schedule->scheduled_end;

        if (Schedule::hasConflict($techId, $start, $end, $schedule->id, $tenantId)) {
            return ApiResponse::message('Conflito de horario: tecnico já possui agendamento neste periodo.', 409);
        }

        try {
            DB::transaction(function () use ($schedule, $validated) {
                $schedule->update($validated);
            });

            return ApiResponse::data($schedule->fresh()->load(['technician:id,name', 'customer:id,name']));
        } catch (\Throwable $e) {
            Log::error('Schedule update failed', ['id' => $schedule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar agendamento', 500);
        }
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        $this->authorize('delete', $schedule);

        try {
            DB::transaction(fn () => $schedule->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Schedule destroy failed', ['id' => $schedule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir agendamento', 500);
        }
    }

    public function unified(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId($request);
            $from = $request->get('from', now()->startOfWeek()->toDateString());
            $to = $request->get('to', now()->endOfWeek()->toDateString());
            $techId = $request->get('technician_id');

            $schedulesQuery = Schedule::with(['technician:id,name', 'customer:id,name', 'workOrder:id,number,os_number,status'])
                ->where('tenant_id', $tenantId)
                ->where('scheduled_start', '>=', $from)
                ->where('scheduled_end', '<=', "{$to} 23:59:59");

            if ($techId) {
                $schedulesQuery->where('technician_id', $techId);
            }

            $schedules = $schedulesQuery->orderBy('scheduled_start')->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'source' => 'schedule',
                    'title' => $s->title,
                    'start' => $s->scheduled_start,
                    'end' => $s->scheduled_end,
                    'status' => $s->status,
                    'technician' => $s->technician,
                    'customer' => $s->customer,
                    'work_order' => $s->workOrder,
                    'notes' => $s->notes,
                    'address' => $s->address,
                ]);

            $crmActivities = collect([]);
            if (class_exists(CrmActivity::class)) {
                $crmQuery = CrmActivity::with(['deal:id,title', 'user:id,name'])
                    ->where('tenant_id', $tenantId)
                    ->whereIn('type', ['reuniao', 'tarefa', 'visita'])
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '>=', $from)
                    ->where('scheduled_at', '<=', "{$to} 23:59:59");

                if ($techId) {
                    $crmQuery->where('user_id', $techId);
                }

                $crmActivities = $crmQuery->orderBy('scheduled_at')->get()
                    ->map(fn ($a) => [
                        'id' => "crm-{$a->id}",
                        'source' => 'crm',
                        'title' => $a->title,
                        'start' => $a->scheduled_at,
                        'end' => $a->scheduled_at,
                        'status' => $a->completed_at ? Schedule::STATUS_COMPLETED : Schedule::STATUS_SCHEDULED,
                        'technician' => $a->user,
                        'customer' => null,
                        'deal' => $a->deal,
                        'notes' => $a->description,
                        'crm_type' => $a->type,
                    ]);
            }

            $serviceCalls = $this->getServiceCalls($tenantId, $from, $to, $techId);
            $all = $schedules->concat($crmActivities)->concat($serviceCalls)->sortBy('start')->values();

            return ApiResponse::data($all, 200, [
                'meta' => [
                    'schedules_count' => $schedules->count(),
                    'crm_activities_count' => $crmActivities->count(),
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ScheduleController unified failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar agenda unificada.', 500);
        }
    }

    private function getServiceCalls(int $tenantId, string $from, string $to, ?int $techId)
    {
        $query = ServiceCall::with(['customer:id,name', 'technician:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$from, "{$to} 23:59:59"]);

        if ($techId) {
            $query->where('technician_id', $techId);
        }

        return $query->orderBy('scheduled_date')->get()
            ->map(fn ($call) => [
                'id' => "call-{$call->id}",
                'source' => 'service_call',
                'title' => "Chamado #{$call->call_number}",
                'start' => $call->scheduled_date,
                'end' => Carbon::parse($call->scheduled_date)->addHour()->toDateTimeString(),
                'status' => $call->status,
                'technician' => $call->technician,
                'customer' => $call->customer,
                'work_order' => null,
                'notes' => $call->observations,
                'address' => $call->address.($call->city ? ", {$call->city}" : ''),
                'priority' => $call->priority,
            ]);
    }

    public function conflicts(CheckScheduleConflictsRequest $request, ConflictDetectionService $service): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();

        $this->ensureTenantUser((int) $validated['technician_id'], $tenantId);

        $result = $service->check(
            $validated['technician_id'],
            $validated['start'],
            $validated['end'],
            $validated['exclude_schedule_id'] ?? null,
            $tenantId
        );

        if ($result['conflict']) {
            return ApiResponse::data([
                'conflict' => true,
                'message' => 'Horario indisponivel.',
                'details' => $result,
            ]);
        }

        return ApiResponse::data([
            'conflict' => false,
            'message' => 'Horario disponivel.',
        ]);
    }

    public function workloadSummary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $from = $request->get('from', now()->startOfWeek()->toDateString());
        $to = $request->get('to', now()->endOfWeek()->toDateString());

        $workloads = Schedule::with('technician:id,name')
            ->where('tenant_id', $tenantId)
            ->whereBetween('scheduled_start', [$from, "{$to} 23:59:59"])
            ->get()
            ->groupBy('technician_id')
            ->map(function ($schedules, $techId) {
                $technician = $schedules->first()->technician;
                $totalMinutes = $schedules->sum(fn ($schedule) => Carbon::parse($schedule->scheduled_end)->diffInMinutes(Carbon::parse($schedule->scheduled_start)));

                return [
                    'technician_id' => $techId,
                    'technician_name' => $technician?->name ?? 'Desconhecido',
                    'total_hours' => round($totalMinutes / 60, 2),
                    'schedules_count' => $schedules->count(),
                ];
            })
            ->values();

        return ApiResponse::data($workloads, 200, [
            'meta' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function suggestRouting(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $pendingWorkOrders = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkOrder::STATUS_OPEN)
            ->with(['customer.locations' => fn ($query) => $query->latest()->limit(1)])
            ->get()
            ->filter(fn ($workOrder) => $workOrder->customer && $workOrder->customer->locations->isNotEmpty())
            ->groupBy(fn ($workOrder) => $workOrder->customer->locations->first()->city ?? 'Indefinido')
            ->map(fn ($group, $city) => [
                'city' => $city,
                'count' => $group->count(),
                'work_orders' => $group->map(fn ($workOrder) => [
                    'id' => $workOrder->id,
                    'number' => $workOrder->os_number ?? $workOrder->number,
                    'internal_number' => $workOrder->number,
                    'os_number' => $workOrder->os_number,
                    'priority' => $workOrder->priority,
                    'customer' => $workOrder->customer->name,
                    'address' => $workOrder->customer->locations->first()->address ?? '',
                ]),
            ])
            ->values();

        return ApiResponse::data($pendingWorkOrders);
    }
}
