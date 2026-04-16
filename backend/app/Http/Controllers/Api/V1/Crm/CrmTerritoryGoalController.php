<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCrmSalesGoalRequest;
use App\Http\Requests\Crm\StoreCrmTerritoryRequest;
use App\Http\Requests\Crm\UpdateCrmSalesGoalRequest;
use App\Http\Requests\Crm\UpdateCrmTerritoryRequest;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\CrmSalesGoal;
use App\Models\CrmTerritory;
use App\Models\CrmTerritoryMember;
use App\Models\Customer;
use App\Support\ApiResponse;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmTerritoryGoalController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ── Territories ────────────────────────────────────────

    public function territories(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.territory.view'), 403);

        $territories = CrmTerritory::where('tenant_id', $this->tenantId($request))
            ->with(['manager:id,name', 'members.user:id,name'])
            ->withCount('customers')
            ->orderBy('name')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return ApiResponse::paginated($territories);
    }

    public function storeTerritory(StoreCrmTerritoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $territory = DB::transaction(function () use ($data, $request) {
            $territory = CrmTerritory::create([
                'tenant_id' => $this->tenantId($request),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'regions' => $data['regions'] ?? null,
                'zip_code_ranges' => $data['zip_code_ranges'] ?? null,
                'manager_id' => $data['manager_id'] ?? null,
            ]);

            if (! empty($data['member_ids'])) {
                foreach ($data['member_ids'] as $userId) {
                    CrmTerritoryMember::create([
                        'territory_id' => $territory->id,
                        'user_id' => $userId,
                    ]);
                }
            }

            return $territory;
        });

        return ApiResponse::data($territory->load('members.user:id,name'), 201);
    }

    public function updateTerritory(UpdateCrmTerritoryRequest $request, CrmTerritory $territory): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($territory, $data) {
            $territory->update(collect($data)->except('member_ids')->toArray());

            if (isset($data['member_ids'])) {
                $territory->members()->delete();
                foreach ($data['member_ids'] as $userId) {
                    CrmTerritoryMember::create([
                        'territory_id' => $territory->id,
                        'user_id' => $userId,
                    ]);
                }
            }
        });

        return ApiResponse::data($territory->load('members.user:id,name'));
    }

    public function destroyTerritory(Request $request, CrmTerritory $territory): JsonResponse
    {
        abort_unless($request->user()->can('crm.territory.manage'), 403);

        try {
            $territory->delete();

            return ApiResponse::message('Território removido');
        } catch (\Exception $e) {
            Log::error('CrmTerritoryGoal destroyTerritory failed', ['territory_id' => $territory->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover território', 500);
        }
    }

    // ── Sales Goals ────────────────────────────────────────

    public function salesGoals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.goal.view'), 403);

        $goals = CrmSalesGoal::where('tenant_id', $this->tenantId($request))
            ->with(['user:id,name', 'territory:id,name'])
            ->when($request->input('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->input('period_type'), fn ($q, $p) => $q->where('period_type', $p))
            ->orderByDesc('period_start')
            ->paginate(min((int) $request->input('per_page', 20), 100));

        return ApiResponse::paginated($goals);
    }

    public function storeSalesGoal(StoreCrmSalesGoalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $goal = CrmSalesGoal::create([
            ...$data,
            'tenant_id' => $this->tenantId($request),
        ]);

        return ApiResponse::data($goal, 201);
    }

    public function updateSalesGoal(UpdateCrmSalesGoalRequest $request, CrmSalesGoal $goal): JsonResponse
    {
        $goal->update($request->validated());

        return ApiResponse::data($goal);
    }

    public function recalculateGoals(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.goal.manage'), 403);

        $tenantId = $this->tenantId($request);
        $goals = CrmSalesGoal::where('tenant_id', $tenantId)
            ->where('period_end', '>=', now())
            ->get();

        foreach ($goals as $goal) {
            $query = CrmDeal::where('tenant_id', $tenantId)
                ->when($goal->user_id, fn ($q, $uid) => $q->where('assigned_to', $uid));

            $goal->achieved_revenue = Decimal::string($query->clone()->won()
                ->whereBetween('won_at', [$goal->period_start, $goal->period_end])
                ->sum('value'));

            $goal->achieved_deals = $query->clone()->won()
                ->whereBetween('won_at', [$goal->period_start, $goal->period_end])
                ->count();

            $goal->achieved_new_customers = Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$goal->period_start, $goal->period_end])
                ->when($goal->user_id, fn ($q, $uid) => $q->where('assigned_seller_id', $uid))
                ->count();

            $goal->achieved_activities = CrmActivity::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$goal->period_start, $goal->period_end])
                ->when($goal->user_id, fn ($q, $uid) => $q->where('user_id', $uid))
                ->whereNotNull('completed_at')
                ->count();

            $goal->save();
        }

        return ApiResponse::message('Metas recalculadas', 200, ['count' => $goals->count()]);
    }

    public function goalsDashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.goal.view'), 403);

        $tenantId = $this->tenantId($request);
        $currentGoals = CrmSalesGoal::where('tenant_id', $tenantId)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->with(['user:id,name', 'territory:id,name'])
            ->get();

        $ranking = $currentGoals->where('user_id', '!=', null)->map(fn ($g) => [
            'user' => $g->user,
            'revenue_progress' => $g->revenueProgress(),
            'deals_progress' => $g->dealsProgress(),
            'target_revenue' => $g->target_revenue,
            'achieved_revenue' => $g->achieved_revenue,
            'target_deals' => $g->target_deals,
            'achieved_deals' => $g->achieved_deals,
        ])->sortByDesc('revenue_progress')->values();

        return ApiResponse::data([
            'goals' => $currentGoals,
            'ranking' => $ranking,
        ]);
    }
}
