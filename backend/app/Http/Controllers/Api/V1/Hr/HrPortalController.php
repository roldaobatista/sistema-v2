<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\RemainingModules\StoreEpiRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrPortalController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function employeePortal(): JsonResponse
    {
        $userId = auth()->id();

        $safeQuery = function (callable $fn) {
            try {
                return $fn();
            } catch (\Throwable) {
                return null;
            }
        };

        $tenantId = $this->tenantId();

        $data = [
            'personal_info' => $safeQuery(fn () => DB::table('users')->where('id', $userId)->first(['name', 'email', 'phone', 'created_at'])),
            'leave_balance' => $safeQuery(fn () => DB::table('leave_balances')->where('user_id', $userId)->where('tenant_id', $tenantId)->first()),
            'recent_payslips' => $safeQuery(fn () => DB::table('payslips')->where('user_id', $userId)->where('tenant_id', $tenantId)->orderByDesc('period')->limit(3)->get()),
            'pending_trainings' => $safeQuery(fn () => DB::table('training_enrollments')->where('user_id', $userId)->where('tenant_id', $tenantId)->where('status', 'pending')->count()) ?? 0,
            'next_review' => $safeQuery(fn () => DB::table('performance_reviews')->where('user_id', $userId)->where('tenant_id', $tenantId)->where('status', 'scheduled')->first(['scheduled_at', 'type'])),
        ];

        return ApiResponse::data($data);
    }

    public function epiList(Request $request): JsonResponse
    {
        $data = DB::table('epi_records')
            ->where('tenant_id', $this->tenantId())
            ->when($request->input('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->orderByDesc('delivered_at')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    public function storeEpi(StoreEpiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $id = DB::table('epi_records')->insertGetId(array_merge($validated, [
                'tenant_id' => $this->tenantId(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            return ApiResponse::data(['id' => $id], 201, ['message' => 'EPI registrado']);
        } catch (\Exception $e) {
            Log::error('EPI record creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar EPI', 500);
        }
    }

    public function productivityGamification(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $month = $request->input('month', now()->month);

        try {
            $leaderboard = DB::table('work_orders')
                ->where('work_orders.tenant_id', $tenantId)
                ->where('work_orders.status', 'completed')
                ->whereMonth('work_orders.completed_at', $month)
                ->join('users', 'work_orders.assigned_to', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.name',
                    DB::raw('COUNT(*) as os_completed'),
                    DB::raw('AVG(DATEDIFF(work_orders.completed_at, work_orders.started_at)) as avg_resolution_days')
                )
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('os_completed')
                ->get()
                ->map(function ($tech, $index) {
                    $tech->rank = $index + 1;
                    $tech->badge = match (true) {
                        $tech->os_completed >= 20 => '🏆 Lenda',
                        $tech->os_completed >= 15 => '⭐ Estrela',
                        $tech->os_completed >= 10 => '🔥 Produtivo',
                        $tech->os_completed >= 5 => '💪 Comprometido',
                        default => '📊 Iniciante',
                    };
                    $tech->points = $tech->os_completed * 100;

                    return $tech;
                });

            return ApiResponse::data($leaderboard);
        } catch (\Throwable $e) {
            Log::warning('Gamification query failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([]);
        }
    }

    public function orgChart(): JsonResponse
    {
        $tenantId = $this->tenantId();

        try {
            $users = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();

            return ApiResponse::data($users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'position' => 'N/A',
                'department' => 'N/A',
                'children' => [],
            ])->values()->toArray());
        } catch (\Throwable $e) {
            Log::warning('Org chart query failed', ['error' => $e->getMessage()]);

            return ApiResponse::data([]);
        }
    }
}
