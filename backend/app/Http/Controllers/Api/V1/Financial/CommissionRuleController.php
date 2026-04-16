<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreCommissionRuleRequest;
use App\Http\Requests\Financial\UpdateCommissionRuleRequest;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\User;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionRuleController extends Controller
{
    use ResolvesCurrentTenant;

    public function rules(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommissionRule::class);

        $query = CommissionRule::where('tenant_id', $this->tenantId())
            ->with('user:id,name');

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($role = CommissionRule::normalizeRole((string) $request->get('applies_to_role', ''))) {
            $query->whereIn('applies_to_role', CommissionRule::aliasesForRole($role));
        }

        return ApiResponse::paginated($query->orderBy('priority')->orderBy('name')->paginate(100));
    }

    public function showRule(CommissionRule $commissionRule): JsonResponse
    {
        $this->authorize('view', $commissionRule);

        if ((int) $commissionRule->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        return ApiResponse::data($commissionRule->load('user:id,name'));
    }

    public function storeRule(StoreCommissionRuleRequest $request): JsonResponse
    {
        $this->authorize('create', CommissionRule::class);

        $validated = $request->validated();

        try {
            $validated['tenant_id'] = $this->tenantId();
            $validated['applies_to'] = $validated['applies_to'] ?? CommissionRule::APPLIES_ALL;
            $validated['applies_to_role'] = $validated['applies_to_role'] ?? CommissionRule::ROLE_TECHNICIAN;
            $validated['applies_when'] = $validated['applies_when'] ?? CommissionRule::WHEN_OS_COMPLETED;

            $rule = DB::transaction(fn () => CommissionRule::create($validated));

            return ApiResponse::data($rule->load('user:id,name'), 201);
        } catch (\Exception $e) {
            Log::error('Falha ao criar regra de comissão', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao criar regra', 500);
        }
    }

    public function updateRule(UpdateCommissionRuleRequest $request, CommissionRule $commissionRule): JsonResponse
    {
        $this->authorize('update', $commissionRule);

        abort_if((int) $commissionRule->tenant_id !== $this->tenantId(), 404);

        $validated = $request->validated();

        try {
            DB::transaction(fn () => $commissionRule->update($validated));

            return ApiResponse::data($commissionRule->fresh()->load('user:id,name'));
        } catch (\Exception $e) {
            Log::error('Falha ao atualizar regra de comissão', ['error' => $e->getMessage(), 'rule_id' => $commissionRule->id]);

            return ApiResponse::message('Erro interno ao atualizar regra', 500);
        }
    }

    public function destroyRule(CommissionRule $commissionRule): JsonResponse
    {
        $this->authorize('delete', $commissionRule);

        abort_if((int) $commissionRule->tenant_id !== $this->tenantId(), 404);

        $eventsCount = CommissionEvent::where('commission_rule_id', $commissionRule->id)->count();
        if ($eventsCount > 0) {
            return ApiResponse::message("Não é possível excluir: existem {$eventsCount} evento(s) vinculados a esta regra", 409);
        }

        try {
            DB::transaction(fn () => $commissionRule->delete());

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Falha ao excluir regra de comissão', ['error' => $e->getMessage(), 'rule_id' => $commissionRule->id]);

            return ApiResponse::message('Erro interno ao excluir regra', 500);
        }
    }

    public function calculationTypes(): JsonResponse
    {
        return ApiResponse::data(CommissionRule::CALCULATION_TYPES);
    }

    public function users(): JsonResponse
    {
        $tenantId = $this->tenantId();

        $users = User::query()
            ->select('users.id', 'users.name')
            ->where(function ($query) use ($tenantId): void {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->distinct()
            ->orderBy('users.name')
            ->get();

        return ApiResponse::data($users);
    }
}
