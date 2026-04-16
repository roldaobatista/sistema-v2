<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ApplyContractAdjustmentRequest;
use App\Http\Requests\Contracts\CreateAddendumRequest;
use App\Http\Requests\Contracts\StoreContractMeasurementRequest;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractsAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    // ─── #38 Reajuste Automático de Contrato ───────────────────

    public function pendingAdjustments(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $contracts = DB::table('recurring_contracts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNotNull('adjustment_index')
            ->whereRaw('next_adjustment_date <= ?', [now()->addDays(30)])
            ->get();

        return ApiResponse::data([
            'pending_count' => $contracts->count(),
            'contracts' => $contracts,
        ]);
    }

    public function applyAdjustment(ApplyContractAdjustmentRequest $request, int $contractId): JsonResponse
    {
        $tenantId = $this->tenantId();

        $contract = DB::table('recurring_contracts')
            ->where('id', $contractId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $contract) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $rate = bcdiv((string) $request->input('index_rate'), '100', 8);
        $oldValue = (string) $contract->monthly_value;
        $newValue = bcmul($oldValue, bcadd('1', $rate, 8), 2);

        try {
            DB::transaction(function () use ($contractId, $tenantId, $request, $oldValue, $newValue) {
                DB::table('contract_adjustments')->insert([
                    'contract_id' => $contractId,
                    'tenant_id' => $tenantId,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'index_rate' => $request->input('index_rate'),
                    'effective_date' => $request->input('effective_date'),
                    'applied_by' => $request->user()->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                DB::table('recurring_contracts')->where('id', $contractId)->where('tenant_id', $tenantId)->update([
                    'monthly_value' => $newValue,
                    'next_adjustment_date' => Carbon::parse($request->input('effective_date'))->addYear(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Contract adjustment failed', ['contract_id' => $contractId, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aplicar reajuste', 500);
        }

        return ApiResponse::data([
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'change_percent' => (float) bcmul($rate, '100', 2),
        ], 200, ['message' => 'Reajuste aplicado.']);
    }

    // ─── #39 Alerta de Vencimento (Churn Prevention) ───────────

    public function churnRisk(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $days = (int) $request->input('days', 60);

        $expiring = DB::table('recurring_contracts')
            ->where('recurring_contracts.tenant_id', $tenantId)
            ->where('recurring_contracts.is_active', true)
            ->whereNotNull('recurring_contracts.end_date')
            ->whereRaw('recurring_contracts.end_date BETWEEN ? AND ?', [now(), now()->addDays($days)])
            ->join('customers', 'recurring_contracts.customer_id', '=', 'customers.id')
            ->select(
                'recurring_contracts.id',
                'recurring_contracts.name',
                'recurring_contracts.customer_id',
                'recurring_contracts.monthly_value',
                'recurring_contracts.start_date',
                'recurring_contracts.end_date',
                'customers.name as customer_name'
            )
            ->orderBy('recurring_contracts.end_date')
            ->get();

        // Categorize risk
        $risk = $expiring->map(function ($c) {
            $daysLeft = now()->diffInDays(Carbon::parse($c->end_date), false);

            return array_merge((array) $c, [
                'days_until_expiry' => $daysLeft,
                'risk_level' => $daysLeft <= 15 ? 'critical' : ($daysLeft <= 30 ? 'high' : 'medium'),
            ]);
        });

        return ApiResponse::data([
            'total_at_risk' => $risk->count(),
            'total_mrr_at_risk' => (float) $risk->reduce(fn (string $carry, $c) => bcadd($carry, (string) ($c['monthly_value'] ?? 0), 2), '0.00'),
            'by_risk_level' => [
                'critical' => $risk->where('risk_level', 'critical')->count(),
                'high' => $risk->where('risk_level', 'high')->count(),
                'medium' => $risk->where('risk_level', 'medium')->count(),
            ],
            'contracts' => $risk,
        ]);
    }

    // ─── #40 Gestão de Aditivos Contratuais ────────────────────

    public function contractAddendums(Request $request, int $contractId): JsonResponse
    {
        $addendums = DB::table('contract_addendums')
            ->where('contract_id', $contractId)
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::data($addendums);
    }

    public function createAddendum(CreateAddendumRequest $request, int $contractId): JsonResponse
    {
        $data = $request->validated();
        $tenantId = $this->tenantId();

        $contractExists = DB::table('recurring_contracts')
            ->where('id', $contractId)
            ->where('tenant_id', $tenantId)
            ->exists();
        if (! $contractExists) {
            return ApiResponse::message('Contrato não encontrado.', 404);
        }

        $id = DB::table('contract_addendums')->insertGetId([
            'contract_id' => $contractId,
            'tenant_id' => $tenantId,
            'type' => $data['type'],
            'description' => $data['description'],
            'new_value' => $data['new_value'] ?? null,
            'new_end_date' => $data['new_end_date'] ?? null,
            'effective_date' => $data['effective_date'],
            'status' => 'pending',
            'created_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::data(['id' => $id, 'message' => 'Addendum created'], 201);
    }

    public function approveAddendum(Request $request, int $addendumId): JsonResponse
    {
        $tenantId = $this->tenantId();

        $addendum = DB::table('contract_addendums')
            ->where('id', $addendumId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $addendum) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        if ($addendum->status !== 'pending') {
            return ApiResponse::message('Aditivo não está pendente de aprovação.', 422);
        }

        try {
            DB::transaction(function () use ($addendumId, $addendum, $tenantId, $request) {
                DB::table('contract_addendums')->where('id', $addendumId)->where('tenant_id', $tenantId)->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);

                $updates = ['updated_at' => now()];
                if ($addendum->new_value) {
                    $updates['monthly_value'] = $addendum->new_value;
                }
                if ($addendum->new_end_date) {
                    $updates['end_date'] = $addendum->new_end_date;
                }
                if ($addendum->type === 'cancellation') {
                    $updates['is_active'] = false;
                }

                DB::table('recurring_contracts')->where('id', $addendum->contract_id)->where('tenant_id', $tenantId)->update($updates);
            });
        } catch (\Throwable $e) {
            Log::error('Addendum approval failed', ['addendum_id' => $addendumId, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar aditivo', 500);
        }

        return ApiResponse::message('Aditivo aprovado e aplicado.');
    }

    // ─── #41 Medição de Contrato (Aceite Parcial) ──────────────

    public function contractMeasurements(Request $request, int $contractId): JsonResponse
    {
        $measurements = DB::table('contract_measurements')
            ->where('contract_id', $contractId)
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('period')
            ->paginate(20);

        return ApiResponse::paginated($measurements);
    }

    public function storeMeasurement(StoreContractMeasurementRequest $request, int $contractId): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $this->tenantId();

        $contractExists = DB::table('recurring_contracts')
            ->where('id', $contractId)
            ->where('tenant_id', $tenantId)
            ->exists();
        if (! $contractExists) {
            return ApiResponse::message('Contrato não encontrado.', 404);
        }

        $totalAccepted = '0';
        $totalRejected = '0';

        foreach ($data['items'] as $item) {
            $total = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
            if ($item['accepted'] ?? true) {
                $totalAccepted = bcadd($totalAccepted, $total, 2);
            } else {
                $totalRejected = bcadd($totalRejected, $total, 2);
            }
        }

        $id = DB::table('contract_measurements')->insertGetId([
            'contract_id' => $contractId,
            'tenant_id' => $tenantId,
            'period' => $data['period'],
            'items' => json_encode($data['items']),
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending_approval',
            'created_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::data([
            'id' => $id,
            'total_accepted' => (float) $totalAccepted,
            'total_rejected' => (float) $totalRejected,
        ], 201);
    }
}
