<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\IndexConsolidatedFinancialRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidatedFinancialController extends Controller
{
    use ResolvesCurrentTenant;

    private function userTenantIds(Request $request): array
    {
        $user = $request->user();
        $ids = $user->tenants()->pluck('tenants.id')->toArray();

        if (empty($ids)) {
            $ids = [$this->resolvedTenantId()];
        }

        return $ids;
    }

    /**
     * GET /financial/consolidated
     * Returns a consolidated financial summary across all tenants the user has access to.
     */
    public function index(IndexConsolidatedFinancialRequest $request): JsonResponse
    {
        try {
            $tenantIds = $this->userTenantIds($request);
            $tenantFilter = $request->input('tenant_id');

            if ($tenantFilter && in_array((int) $tenantFilter, $tenantIds, true)) {
                $tenantIds = [(int) $tenantFilter];
            }

            if (empty($tenantIds)) {
                return ApiResponse::message('Nenhum tenant disponível.', 403);
            }

            $today = Carbon::today();
            $startMonth = $today->copy()->startOfMonth();
            $endMonth = $today->copy()->endOfMonth();
            $tenantCount = count($tenantIds);

            $paid = FinancialStatus::PAID->value;
            $cancelled = FinancialStatus::CANCELLED->value;
            $renegotiated = FinancialStatus::RENEGOTIATED->value;

            $receivedByTenant = Payment::query()
                ->whereIn('tenant_id', $tenantIds)
                ->where('payable_type', AccountReceivable::class)
                ->whereBetween('payment_date', [$startMonth->toDateString(), $endMonth->toDateString().' 23:59:59'])
                ->select('tenant_id')
                ->selectRaw('SUM(amount) as total')
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');

            $receivedLegacyByTenant = AccountReceivable::query()
                ->whereIn('tenant_id', $tenantIds)
                ->where('amount_paid', '>', 0)
                ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$startMonth->toDateString(), $endMonth->toDateString().' 23:59:59'])
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('payments')
                        ->whereColumn('payments.payable_id', 'accounts_receivable.id')
                        ->where('payments.payable_type', AccountReceivable::class);
                })
                ->select('tenant_id')
                ->selectRaw('SUM(amount_paid) as total')
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');

            $paidByTenant = Payment::query()
                ->whereIn('tenant_id', $tenantIds)
                ->where('payable_type', AccountPayable::class)
                ->whereBetween('payment_date', [$startMonth->toDateString(), $endMonth->toDateString().' 23:59:59'])
                ->select('tenant_id')
                ->selectRaw('SUM(amount) as total')
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');

            $paidLegacyByTenant = AccountPayable::query()
                ->whereIn('tenant_id', $tenantIds)
                ->where('amount_paid', '>', 0)
                ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$startMonth->toDateString(), $endMonth->toDateString().' 23:59:59'])
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('payments')
                        ->whereColumn('payments.payable_id', 'accounts_payable.id')
                        ->where('payments.payable_type', AccountPayable::class);
                })
                ->select('tenant_id')
                ->selectRaw('SUM(amount_paid) as total')
                ->groupBy('tenant_id')
                ->pluck('total', 'tenant_id');

            // Receivables summary per tenant
            $receivablesByTenant = AccountReceivable::whereIn('tenant_id', $tenantIds)
                ->select('tenant_id')
                ->selectRaw('SUM(CASE WHEN status NOT IN (?,?,?) THEN (amount - amount_paid) ELSE 0 END) as open_total', [$paid, $cancelled, $renegotiated])
                ->selectRaw('SUM(CASE WHEN status NOT IN (?,?,?) AND due_date < ? THEN (amount - amount_paid) ELSE 0 END) as overdue_total', [$paid, $cancelled, $renegotiated, $today])
                ->groupBy('tenant_id')
                ->limit($tenantCount)
                ->get()
                ->keyBy('tenant_id');

            // Payables summary per tenant
            $payablesByTenant = AccountPayable::whereIn('tenant_id', $tenantIds)
                ->select('tenant_id')
                ->selectRaw('SUM(CASE WHEN status NOT IN (?,?,?) THEN (amount - amount_paid) ELSE 0 END) as open_total', [$paid, $cancelled, $renegotiated])
                ->selectRaw('SUM(CASE WHEN status NOT IN (?,?,?) AND due_date < ? THEN (amount - amount_paid) ELSE 0 END) as overdue_total', [$paid, $cancelled, $renegotiated, $today])
                ->groupBy('tenant_id')
                ->limit($tenantCount)
                ->get()
                ->keyBy('tenant_id');

            // Expenses summary per tenant (current month)
            $expensesByTenant = Expense::whereIn('tenant_id', $tenantIds)
                ->whereNull('deleted_at')
                ->where('status', ExpenseStatus::APPROVED)
                ->whereBetween('expense_date', [$startMonth, $endMonth])
                ->select('tenant_id')
                ->selectRaw('SUM(amount) as total')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('tenant_id')
                ->limit($tenantCount)
                ->get()
                ->keyBy('tenant_id');

            // OS invoiced this month per tenant
            $invoicedByTenant = WorkOrder::whereIn('tenant_id', $tenantIds)
                ->where('status', WorkOrderStatus::INVOICED->value)
                ->whereBetween('updated_at', [$startMonth, $endMonth])
                ->select('tenant_id')
                ->selectRaw('SUM(total) as total')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('tenant_id')
                ->limit($tenantCount)
                ->get()
                ->keyBy('tenant_id');

            // Tenants info
            $tenants = DB::table('tenants')
                ->whereIn('id', $tenantIds)
                ->select('id', 'name', 'document')
                ->limit($tenantCount)
                ->get()
                ->keyBy('id');

            $perTenant = [];
            $totals = [
                'receivables_open' => '0.00',
                'receivables_overdue' => '0.00',
                'received_month' => '0.00',
                'payables_open' => '0.00',
                'payables_overdue' => '0.00',
                'paid_month' => '0.00',
                'expenses_month' => '0.00',
                'invoiced_month' => '0.00',
            ];

            foreach ($tenantIds as $tid) {
                $rec = $receivablesByTenant[$tid] ?? null;
                $pay = $payablesByTenant[$tid] ?? null;
                $exp = $expensesByTenant[$tid] ?? null;
                $inv = $invoicedByTenant[$tid] ?? null;
                $tenant = $tenants[$tid] ?? null;

                $row = [
                    'tenant_id' => $tid,
                    'tenant_name' => $tenant->name ?? "Tenant #{$tid}",
                    'tenant_document' => $tenant->document ?? null,
                    'receivables_open' => bcadd((string) ($rec->open_total ?? 0), '0', 2),
                    'receivables_overdue' => bcadd((string) ($rec->overdue_total ?? 0), '0', 2),
                    'received_month' => bcadd((string) ($receivedByTenant[$tid] ?? 0), (string) ($receivedLegacyByTenant[$tid] ?? 0), 2),
                    'payables_open' => bcadd((string) ($pay->open_total ?? 0), '0', 2),
                    'payables_overdue' => bcadd((string) ($pay->overdue_total ?? 0), '0', 2),
                    'paid_month' => bcadd((string) ($paidByTenant[$tid] ?? 0), (string) ($paidLegacyByTenant[$tid] ?? 0), 2),
                    'expenses_month' => bcadd((string) ($exp->total ?? 0), '0', 2),
                    'invoiced_month' => bcadd((string) ($inv->total ?? 0), '0', 2),
                ];

                $perTenant[] = $row;

                foreach ($totals as $key => &$val) {
                    $val = bcadd($val, $row[$key], 2);
                }
            }

            return ApiResponse::data([
                'period' => $startMonth->format('Y-m'),
                'totals' => $totals,
                'balance' => bcsub($totals['receivables_open'], $totals['payables_open'], 2),
                'per_tenant' => $perTenant,
            ]);
        } catch (\Exception $e) {
            Log::error('Consolidated financial failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar dados financeiros consolidados.', 500);
        }
    }
}
