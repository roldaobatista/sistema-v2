<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\StartTimeEntryRequest;
use App\Http\Requests\Technician\StoreTimeEntryRequest;
use App\Http\Requests\Technician\UpdateTimeEntryRequest;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TimeEntryController extends Controller
{
    use ResolvesCurrentTenant;

    private function hasRunningEntry(int $tenantId, int $technicianId, ?int $excludeEntryId = null): bool
    {
        return TimeEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('technician_id', $technicianId)
            ->whereNull('ended_at')
            ->when($excludeEntryId, fn ($query) => $query->where('id', '!=', $excludeEntryId))
            ->exists();
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
        $tenantId = $this->tenantId();

        $query = TimeEntry::with([
            'technician:id,name',
            'workOrder:id,number,os_number',
        ])->where('tenant_id', $tenantId);

        if ($techId = $request->get('technician_id')) {
            $query->where('technician_id', $techId);
        }

        if ($woId = $request->get('work_order_id')) {
            $query->where('work_order_id', $woId);
        }

        if ($from = $request->get('from')) {
            $query->where('started_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('started_at', '<=', $to);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $entries = $query->orderByDesc('started_at')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        return ApiResponse::paginated($entries);
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $this->ensureTenantUser((int) $validated['technician_id'], $tenantId);

        $isOpenEntry = ! array_key_exists('ended_at', $validated) || $validated['ended_at'] === null;
        if ($isOpenEntry && $this->hasRunningEntry($tenantId, (int) $validated['technician_id'])) {
            return ApiResponse::message('Tecnico já possui apontamento em andamento.', 409);
        }

        try {
            $entry = DB::transaction(function () use ($validated, $tenantId) {
                return TimeEntry::create([...$validated, 'tenant_id' => $tenantId]);
            });

            return ApiResponse::data($entry->load(['technician:id,name', 'workOrder:id,number,os_number']), 201);
        } catch (\Throwable $e) {
            Log::error('TimeEntry store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar apontamento.', 500);
        }
    }

    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->tenant_id === $this->tenantId(), 404);

        try {
            $timeEntry->update($request->validated());

            return ApiResponse::data($timeEntry->fresh()->load(['technician:id,name', 'workOrder:id,number,os_number']));
        } catch (\Throwable $e) {
            Log::error('TimeEntry update failed', ['id' => $timeEntry->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar apontamento.', 500);
        }
    }

    public function destroy(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->tenant_id === $this->tenantId(), 404);

        try {
            DB::transaction(fn () => $timeEntry->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('TimeEntry destroy failed', ['id' => $timeEntry->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir apontamento.', 500);
        }
    }

    public function start(StartTimeEntryRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        if ($this->hasRunningEntry($tenantId, (int) $request->user()->id)) {
            return ApiResponse::message('Voce já possui apontamento em andamento.', 409);
        }

        $entry = TimeEntry::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'technician_id' => $request->user()->id,
            'started_at' => now(),
        ]);

        return ApiResponse::data($entry->load(['workOrder:id,number,os_number']), 201);
    }

    public function stop(Request $request, TimeEntry $timeEntry): JsonResponse
    {
        abort_unless($timeEntry->tenant_id === $this->tenantId(), 404);

        if ($timeEntry->ended_at) {
            return ApiResponse::message('Apontamento já finalizado.', 422);
        }

        $user = $request->user();
        $canManageOthers = $user->hasRole(Role::SUPER_ADMIN) || $user->can('technicians.time_entry.update');
        if ($timeEntry->technician_id !== $user->id && ! $canManageOthers) {
            return ApiResponse::message('Sem permissao para finalizar este apontamento.', 403);
        }

        $timeEntry->update(['ended_at' => now()]);

        return ApiResponse::data($timeEntry->fresh()->load(['workOrder:id,number,os_number']));
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $from = $request->get('from', now()->startOfWeek()->toDateString());
        $to = $request->get('to', now()->endOfWeek()->toDateString());

        $entries = TimeEntry::selectRaw('technician_id, type, SUM(duration_minutes) as total_minutes, COUNT(*) as entries_count')
            ->where('tenant_id', $tenantId)
            ->whereBetween('started_at', [$from, "{$to} 23:59:59"])
            ->whereNotNull('ended_at')
            ->groupBy('technician_id', 'type')
            ->with('technician:id,name')
            ->get();

        return ApiResponse::data($entries, 200, [
            'meta' => compact('from', 'to'),
        ]);
    }
}
