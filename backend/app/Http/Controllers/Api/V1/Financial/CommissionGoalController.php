<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\CommissionEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreCommissionGoalRequest;
use App\Http\Requests\Financial\UpdateCommissionGoalRequest;
use App\Models\CommissionEvent;
use App\Models\CommissionGoal;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionGoalController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionGoal::class);

        $query = CommissionGoal::with('user:id,name')
            ->where('tenant_id', $this->tenantId());

        if ($request->boolean('my')) {
            $query->where('user_id', auth()->id());
        }

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($period = $request->get('period')) {
            $query->where('period', $period);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $goals = $query->orderByDesc('period')->paginate(min((int) $request->input('per_page', 25), 100))->through(function ($goal) {
            $goal->achievement_pct = $goal->progress_percentage;
            $goal->user_name = $goal->user?->name;

            return $goal;
        });

        return ApiResponse::paginated($goals);
    }

    public function store(StoreCommissionGoalRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionGoal::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $goalType = $validated['type'] ?? 'revenue';

        $existing = CommissionGoal::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->where('period', $validated['period'])
            ->where('type', $goalType)
            ->exists();

        if ($existing) {
            return ApiResponse::message('já existe uma meta deste tipo para este Usuário e periodo.', 422);
        }

        try {
            $goal = DB::transaction(function () use ($tenantId, $validated) {
                return CommissionGoal::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $validated['user_id'],
                    'period' => $validated['period'],
                    'target_amount' => $validated['target_amount'],
                    'type' => $validated['type'] ?? 'revenue',
                    'bonus_percentage' => $validated['bonus_percentage'] ?? null,
                    'bonus_amount' => $validated['bonus_amount'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'achieved_amount' => 0,
                ]);
            });

            return ApiResponse::data($goal->load('user:id,name'), 201);
        } catch (\Throwable $e) {
            Log::error('Commission goal store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar meta.', 500);
        }
    }

    public function update(UpdateCommissionGoalRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $goal = CommissionGoal::where('tenant_id', $this->tenantId())->find($id);

        if (! $goal) {
            return ApiResponse::message('Meta nao encontrada.', 404);
        }

        $this->authorize('update', $goal);

        $updates = [];
        if (isset($validated['target_amount'])) {
            $updates['target_amount'] = $validated['target_amount'];
        }
        if (isset($validated['type'])) {
            $updates['type'] = $validated['type'];
        }
        if (array_key_exists('bonus_percentage', $validated)) {
            $updates['bonus_percentage'] = $validated['bonus_percentage'];
        }
        if (array_key_exists('bonus_amount', $validated)) {
            $updates['bonus_amount'] = $validated['bonus_amount'];
        }
        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        DB::transaction(fn () => $goal->update($updates));

        return ApiResponse::data($goal->fresh()->load('user:id,name'));
    }

    public function refreshAchievement(int $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $goal = CommissionGoal::where('tenant_id', $tenantId)->find($id);

        if (! $goal) {
            return ApiResponse::message('Meta nao encontrada.', 404);
        }

        try {
            $driver = DB::getDriverName();
            $periodFilter = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at) = ?"
                : "DATE_FORMAT(created_at, '%Y-%m') = ?";

            $achieved = CommissionEvent::where('tenant_id', $tenantId)
                ->where('user_id', $goal->user_id)
                ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
                ->whereRaw($periodFilter, [$goal->period])
                ->sum('commission_amount');

            $goal->update(['achieved_amount' => $achieved]);

            return ApiResponse::data([
                'achieved_amount' => (float) $achieved,
                'target_amount' => (float) $goal->target_amount,
                'achievement_pct' => $goal->fresh()->progress_percentage,
            ]);
        } catch (\Throwable $e) {
            Log::error('Commission goal refresh failed', [
                'goal_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao recalcular meta.', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $goal = CommissionGoal::where('tenant_id', $this->tenantId())->find($id);

            if (! $goal) {
                return ApiResponse::message('Meta nao encontrada.', 404);
            }

            $this->authorize('delete', $goal);

            DB::transaction(fn () => $goal->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Commission goal destroy failed', [
                'goal_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir meta.', 500);
        }
    }
}
