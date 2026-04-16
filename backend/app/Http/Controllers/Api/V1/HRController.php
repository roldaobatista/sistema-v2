<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\BatchScheduleEntryRequest;
use App\Http\Requests\HR\ClockInRequest;
use App\Http\Requests\HR\ClockOutRequest;
use App\Http\Requests\HR\StoreScheduleEntryRequest;
use App\Http\Requests\HR\StoreTrainingRequest;
use App\Http\Requests\HR\UpdateTrainingRequest;
use App\Models\PerformanceReview;
use App\Models\Role;
use App\Models\TimeClockEntry;
use App\Models\Training;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HRController extends Controller
{
    use ResolvesCurrentTenant;
    // ─── WORK SCHEDULES ──────────────────────────────────────────

    public function indexSchedules(Request $request): JsonResponse
    {
        $query = WorkSchedule::where('tenant_id', $this->tenantId())
            ->with('user:id,name');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        return ApiResponse::paginated($query->orderBy('date')->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    public function storeSchedule(StoreScheduleEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $schedule = DB::transaction(function () use ($validated) {
                $tenantId = $this->tenantId();

                $schedule = WorkSchedule::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $validated['user_id'])
                    ->whereDate('date', $validated['date'])
                    ->first();

                if ($schedule) {
                    $schedule->fill($validated);
                    $schedule->tenant_id = $tenantId;
                    $schedule->save();

                    return $schedule;
                }

                return WorkSchedule::create($validated + ['tenant_id' => $tenantId]);
            });

            return ApiResponse::data($schedule, 201, ['message' => 'Escala salva com sucesso']);
        } catch (\Exception $e) {
            Log::error('WorkSchedule create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao salvar escala', 500);
        }
    }

    public function batchSchedule(BatchScheduleEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->tenantId();
            DB::transaction(function () use ($validated, $tenantId) {
                foreach ($validated['schedules'] as $data) {
                    $schedule = WorkSchedule::query()
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $data['user_id'])
                        ->whereDate('date', $data['date'])
                        ->first();

                    if ($schedule) {
                        $schedule->fill($data);
                        $schedule->tenant_id = $tenantId;
                        $schedule->save();

                        continue;
                    }

                    WorkSchedule::create($data + ['tenant_id' => $tenantId]);
                }
            });

            return ApiResponse::message(count($validated['schedules']).' escalas salvas');
        } catch (\Exception $e) {
            Log::error('WorkSchedule batch failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao salvar escalas em lote', 500);
        }
    }

    // ─── TIME CLOCK ──────────────────────────────────────────────

    public function clockIn(ClockInRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenantId = $this->tenantId();

        $openEntry = TimeClockEntry::where('user_id', $request->user()->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('clock_out')
            ->first();

        if ($openEntry) {
            return ApiResponse::message('Já existe um ponto aberto. Registre a saída primeiro.', 422);
        }

        try {
            $entry = DB::transaction(fn () => TimeClockEntry::create([
                'tenant_id' => $tenantId,
                'user_id' => $request->user()->id,
                'clock_in' => now(),
                'latitude_in' => $validated['latitude'] ?? null,
                'longitude_in' => $validated['longitude'] ?? null,
                'type' => $validated['type'] ?? 'regular',
            ]));

            return ApiResponse::data($entry, 201, ['message' => 'Ponto de entrada registrado']);
        } catch (\Exception $e) {
            Log::error('Clock-in failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar ponto', 500);
        }
    }

    public function clockOut(ClockOutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $openEntry = TimeClockEntry::where('user_id', $request->user()->id)
            ->where('tenant_id', $this->tenantId())
            ->whereNull('clock_out')
            ->first();

        if (! $openEntry) {
            return ApiResponse::message('Nenhum ponto aberto encontrado.', 422);
        }

        try {
            DB::transaction(fn () => $openEntry->update([
                'clock_out' => now(),
                'latitude_out' => $validated['latitude'] ?? null,
                'longitude_out' => $validated['longitude'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]));

            return ApiResponse::data($openEntry->fresh(), 200, ['message' => 'Ponto de saída registrado']);
        } catch (\Exception $e) {
            Log::error('Clock-out failed', ['user_id' => $request->user()->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar saída', 500);
        }
    }

    public function myClockHistory(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            TimeClockEntry::where('user_id', $request->user()->id)
                ->where('tenant_id', $this->tenantId())
                ->orderByDesc('clock_in')
                ->paginate(min((int) $request->input('per_page', 30), 100))
        );
    }

    public function allClockEntries(Request $request): JsonResponse
    {
        $query = TimeClockEntry::where('tenant_id', $this->tenantId())
            ->with('user:id,name');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('clock_in', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('clock_in', '<=', $request->date_to);
        }

        return ApiResponse::paginated($query->orderByDesc('clock_in')->paginate(min((int) $request->input('per_page', 50), 100)));
    }

    // ─── TRAININGS ───────────────────────────────────────────────

    public function indexTrainings(Request $request): JsonResponse
    {
        $query = Training::where('tenant_id', $this->tenantId())
            ->with('user:id,name');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        return ApiResponse::paginated($query->orderByDesc('completion_date')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeTraining(StoreTrainingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $validated['tenant_id'] = $this->tenantId();
            $training = DB::transaction(fn () => Training::create($validated));

            return ApiResponse::data($training, 201, ['message' => 'Treinamento registrado']);
        } catch (\Exception $e) {
            Log::error('Training create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar treinamento', 500);
        }
    }

    public function updateTraining(UpdateTrainingRequest $request, Training $training): JsonResponse
    {
        abort_if((int) $training->tenant_id !== $this->tenantId(), 404);

        try {
            DB::transaction(fn () => $training->update($request->validated()));

            return ApiResponse::data($training->fresh(), 200, ['message' => 'Treinamento atualizado']);
        } catch (\Exception $e) {
            Log::error('Training update failed', ['training_id' => $training->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar treinamento', 500);
        }
    }

    public function showTraining(Training $training): JsonResponse
    {
        abort_if((int) $training->tenant_id !== $this->tenantId(), 404);
        $training->load('user:id,name');

        return ApiResponse::data($training);
    }

    public function destroyTraining(Training $training): JsonResponse
    {
        abort_if((int) $training->tenant_id !== $this->tenantId(), 404);

        try {
            $training->delete();

            return ApiResponse::message('Treinamento excluído com sucesso');
        } catch (\Exception $e) {
            Log::error('Training delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir treinamento', 500);
        }
    }

    // ─── PERFORMANCE REVIEWS ─────────────────────────────────────

    // Performance Review methods moved to PerformanceReviewController

    // ─── HR DASHBOARD ────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $expiringTrainings = Training::where('tenant_id', $tenantId)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addMonth())
            ->where('status', '!=', 'expired')
            ->count();

        $activeClocks = TimeClockEntry::where('tenant_id', $tenantId)
            ->whereNull('clock_out')
            ->count();

        $pendingReviews = PerformanceReview::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->count();

        $totalTechnicians = User::where('tenant_id', $tenantId)
            ->whereHas('roles', function ($q) {
                $q->where('name', Role::TECNICO);
            })
            ->count();

        return ApiResponse::data([
            'expiring_trainings' => $expiringTrainings,
            'trainings_due' => $expiringTrainings,
            'active_clocks' => $activeClocks,
            'clocked_in_today' => $activeClocks,
            'pending_reviews' => $pendingReviews,
            'total_technicians' => $totalTechnicians,
        ]);
    }
}
