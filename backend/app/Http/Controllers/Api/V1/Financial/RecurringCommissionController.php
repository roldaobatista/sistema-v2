<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\CommissionEventStatus;
use App\Enums\RecurringCommissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreRecurringCommissionRequest;
use App\Http\Requests\Financial\UpdateRecurringCommissionStatusRequest;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\RecurringCommission;
use App\Models\RecurringContract;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringCommissionController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RecurringCommission::class);

        $query = RecurringCommission::where('recurring_commissions.tenant_id', $this->tenantId())
            ->join('users', 'recurring_commissions.user_id', '=', 'users.id')
            ->join('commission_rules', 'recurring_commissions.commission_rule_id', '=', 'commission_rules.id')
            ->leftJoin('recurring_contracts', 'recurring_commissions.recurring_contract_id', '=', 'recurring_contracts.id')
            ->select(
                'recurring_commissions.*',
                'users.name as user_name',
                'commission_rules.name as rule_name',
                'commission_rules.calculation_type',
                'commission_rules.value as rule_value',
                'recurring_contracts.name as contract_name'
            );

        if ($status = $request->get('status')) {
            $query->where('recurring_commissions.status', $status);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('recurring_commissions.created_at')->paginate(min((int) $request->get('per_page', 50), 100))
        );
    }

    public function store(StoreRecurringCommissionRequest $request): JsonResponse
    {
        $this->authorize('create', RecurringCommission::class);

        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $user = User::where('tenant_id', $tenantId)->find($validated['user_id']);
        $contract = RecurringContract::where('tenant_id', $tenantId)->find($validated['recurring_contract_id']);
        $rule = CommissionRule::where('tenant_id', $tenantId)->find($validated['commission_rule_id']);

        if (! $user || ! $contract || ! $rule) {
            return ApiResponse::message('Nao foi possivel validar os dados da recorrencia.', 422);
        }

        if (! ($contract->is_active ?? false)) {
            return ApiResponse::message('O contrato recorrente informado nao esta ativo.', 422);
        }

        if (! ($rule->active ?? false)) {
            return ApiResponse::message('A regra de comissao informada precisa estar ativa.', 422);
        }

        $existing = RecurringCommission::where('tenant_id', $tenantId)
            ->where('user_id', $validated['user_id'])
            ->where('recurring_contract_id', $validated['recurring_contract_id'])
            ->where('status', RecurringCommissionStatus::ACTIVE)
            ->exists();

        if ($existing) {
            return ApiResponse::message('Ja existe comissao recorrente ativa para este usuario e contrato.', 422);
        }

        try {
            $recurring = DB::transaction(function () use ($tenantId, $validated) {
                return RecurringCommission::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $validated['user_id'],
                    'recurring_contract_id' => $validated['recurring_contract_id'],
                    'commission_rule_id' => $validated['commission_rule_id'],
                    'status' => RecurringCommissionStatus::ACTIVE,
                ]);
            });

            $resource = RecurringCommission::where('recurring_commissions.tenant_id', $tenantId)
                ->where('recurring_commissions.id', $recurring->id)
                ->join('users', 'recurring_commissions.user_id', '=', 'users.id')
                ->join('commission_rules', 'recurring_commissions.commission_rule_id', '=', 'commission_rules.id')
                ->leftJoin('recurring_contracts', 'recurring_commissions.recurring_contract_id', '=', 'recurring_contracts.id')
                ->select(
                    'recurring_commissions.*',
                    'users.name as user_name',
                    'commission_rules.name as rule_name',
                    'commission_rules.calculation_type',
                    'commission_rules.value as rule_value',
                    'recurring_contracts.name as contract_name'
                )
                ->first();

            return ApiResponse::data($resource, 201, [
                'message' => 'Comissao recorrente criada.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Falha ao criar comissao recorrente', [
                'tenant_id' => $tenantId,
                'user_id' => $validated['user_id'] ?? null,
                'recurring_contract_id' => $validated['recurring_contract_id'] ?? null,
                'commission_rule_id' => $validated['commission_rule_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao criar comissao recorrente.', 500);
        }
    }

    public function updateStatus(UpdateRecurringCommissionStatusRequest $request, int $id): JsonResponse
    {

        $validated = $request->validated();

        $recurring = RecurringCommission::where('tenant_id', $this->tenantId())->find($id);

        if (! $recurring) {
            return ApiResponse::message('Comissao recorrente nao encontrada.', 404);
        }

        $this->authorize('update', $recurring);

        $validTransitions = [
            RecurringCommissionStatus::ACTIVE->value => [RecurringCommissionStatus::PAUSED, RecurringCommissionStatus::TERMINATED],
            RecurringCommissionStatus::PAUSED->value => [RecurringCommissionStatus::ACTIVE, RecurringCommissionStatus::TERMINATED],
            RecurringCommissionStatus::TERMINATED->value => [],
        ];

        $targetStatus = RecurringCommissionStatus::from($validated['status']);
        $allowed = $validTransitions[$recurring->status->value];

        if (! in_array($targetStatus, $allowed, true)) {
            return ApiResponse::message("Transicao de status invalida: {$recurring->status->value} -> {$targetStatus->value}", 422);
        }

        try {
            DB::transaction(function () use ($recurring, $targetStatus) {
                $recurring->update([
                    'status' => $targetStatus,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::error('Falha ao atualizar status de comissao recorrente', [
                'tenant_id' => $this->tenantId(),
                'recurring_id' => $id,
                'target_status' => $targetStatus->value,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao atualizar status da comissao recorrente.', 500);
        }

        return ApiResponse::message('Status atualizado.');
    }

    public function processMonthly(): JsonResponse
    {
        $this->authorize('create', RecurringCommission::class);

        $tenantId = $this->tenantId();
        $now = now();
        $period = $now->format('Y-m');

        $recurrings = RecurringCommission::where('tenant_id', $tenantId)
            ->where('status', RecurringCommissionStatus::ACTIVE)
            ->get();

        try {
            $generated = 0;

            DB::transaction(function () use ($recurrings, $tenantId, $now, $period, &$generated) {
                foreach ($recurrings as $recurring) {
                    if ($recurring->last_generated_at && $recurring->last_generated_at->format('Y-m') === $period) {
                        continue;
                    }

                    $contract = RecurringContract::where('tenant_id', $tenantId)
                        ->find($recurring->recurring_contract_id);

                    if (! $contract || ! ($contract->is_active ?? false)) {
                        continue;
                    }

                    $rule = $recurring->commissionRule;
                    if (! $rule || ! $rule->active) {
                        continue;
                    }

                    $baseAmount = (string) ($contract->monthly_value ?? $contract->total_value ?? 0);
                    if (bccomp($baseAmount, '0', 2) <= 0) {
                        continue;
                    }

                    $workOrder = WorkOrder::where('tenant_id', $tenantId)
                        ->where('recurring_contract_id', $recurring->recurring_contract_id)
                        ->whereYear('created_at', $now->year)
                        ->whereMonth('created_at', $now->month)
                        ->orderByDesc('id')
                        ->first();

                    if (! $workOrder) {
                        continue;
                    }

                    $commissionAmount = $rule->calculateCommission($baseAmount, [
                        'gross' => $baseAmount,
                        'expenses' => 0,
                        'displacement' => 0,
                        'products_total' => 0,
                        'services_total' => $baseAmount,
                        'cost' => 0,
                    ]);

                    if (bccomp((string) $commissionAmount, '0', 2) <= 0) {
                        continue;
                    }

                    CommissionEvent::create([
                        'tenant_id' => $tenantId,
                        'commission_rule_id' => $rule->id,
                        'work_order_id' => $workOrder->id,
                        'user_id' => $recurring->user_id,
                        'base_amount' => $baseAmount,
                        'commission_amount' => $commissionAmount,
                        'status' => CommissionEventStatus::PENDING,
                        'notes' => 'Recorrente: OS '.($workOrder->os_number ?? $workOrder->number)." / Contrato #{$recurring->recurring_contract_id} ({$period})",
                    ]);

                    $recurring->update([
                        'last_generated_at' => $now->toDateString(),
                    ]);

                    $generated++;
                }
            });

            return ApiResponse::data([
                'generated' => $generated,
            ], 200, [
                'message' => "{$generated} comissoes recorrentes geradas.",
            ]);
        } catch (\Throwable $exception) {
            Log::error('Falha ao processar comissoes recorrentes', [
                'tenant_id' => $tenantId,
                'period' => $period,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao processar comissoes recorrentes.', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {

        $recurring = RecurringCommission::where('tenant_id', $this->tenantId())->find($id);

        if (! $recurring) {
            return ApiResponse::message('Comissao recorrente nao encontrada.', 404);
        }

        $this->authorize('delete', $recurring);

        try {
            DB::transaction(fn () => $recurring->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $exception) {
            Log::error('Falha ao excluir comissao recorrente', [
                'tenant_id' => $this->tenantId(),
                'recurring_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::message('Erro interno ao excluir comissao recorrente.', 500);
        }
    }
}
