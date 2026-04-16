<?php

namespace App\Actions\Report;

use App\Enums\ExpenseStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\WorkOrderItem;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class GenerateProfitabilityReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());
        $osNumber = $this->osNumberFilter($filters);
        $branchId = $this->branchId($filters);

        $revenuePaymentsQuery = Payment::query()
            ->join('accounts_receivable as ar_profit', 'payments.payable_id', '=', 'ar_profit.id')
            ->leftJoin('work_orders as wo_profit', 'ar_profit.work_order_id', '=', 'wo_profit.id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.payable_type', AccountReceivable::class)
            ->where('ar_profit.tenant_id', $tenantId)
            ->where('ar_profit.status', '!=', AccountReceivable::STATUS_CANCELLED)
            ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"]);
        $this->applyReceivablePaymentReportFilters($revenuePaymentsQuery, $osNumber, $branchId, 'ar_profit', 'wo_profit');
        $revenuePayments = Decimal::string($revenuePaymentsQuery->sum('payments.amount'));

        $legacyRevenueQuery = DB::table('accounts_receivable as ar_legacy_profit')
            ->leftJoin('work_orders as wo_legacy_profit', 'ar_legacy_profit.work_order_id', '=', 'wo_legacy_profit.id')
            ->where('ar_legacy_profit.tenant_id', $tenantId)
            ->whereNull('ar_legacy_profit.deleted_at')
            ->where('ar_legacy_profit.status', '!=', AccountReceivable::STATUS_CANCELLED)
            ->where('ar_legacy_profit.amount_paid', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('payments')
                    ->whereColumn('payments.payable_id', 'ar_legacy_profit.id')
                    ->where('payments.payable_type', AccountReceivable::class);
            })
            ->whereBetween(DB::raw('COALESCE(ar_legacy_profit.paid_at, ar_legacy_profit.due_date)'), [$from, "{$to} 23:59:59"]);
        $this->applyReceivablePaymentReportFilters($legacyRevenueQuery, $osNumber, $branchId, 'ar_legacy_profit', 'wo_legacy_profit');
        $legacyRevenue = Decimal::string($legacyRevenueQuery->sum('ar_legacy_profit.amount_paid'));
        $revenue = bcadd($revenuePayments, $legacyRevenue, 2);

        $costPaymentsQuery = Payment::query()
            ->join('accounts_payable as ap_profit', 'payments.payable_id', '=', 'ap_profit.id')
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.payable_type', AccountPayable::class)
            ->where('ap_profit.tenant_id', $tenantId)
            ->where('ap_profit.status', '!=', AccountPayable::STATUS_CANCELLED)
            ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"]);
        $this->applyPayableAliasIdentifierFilter($costPaymentsQuery, $osNumber, 'ap_profit');
        $costPayments = Decimal::string($costPaymentsQuery->sum('payments.amount'));

        $legacyCostsQuery = DB::table('accounts_payable as ap_legacy_profit')
            ->where('ap_legacy_profit.tenant_id', $tenantId)
            ->whereNull('ap_legacy_profit.deleted_at')
            ->where('ap_legacy_profit.status', '!=', AccountPayable::STATUS_CANCELLED)
            ->where('ap_legacy_profit.amount_paid', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('payments')
                    ->whereColumn('payments.payable_id', 'ap_legacy_profit.id')
                    ->where('payments.payable_type', AccountPayable::class);
            })
            ->whereBetween(DB::raw('COALESCE(ap_legacy_profit.paid_at, ap_legacy_profit.due_date)'), [$from, "{$to} 23:59:59"]);
        $this->applyPayableAliasIdentifierFilter($legacyCostsQuery, $osNumber, 'ap_legacy_profit');
        $legacyCosts = Decimal::string($legacyCostsQuery->sum('ap_legacy_profit.amount_paid'));
        $costs = bcadd($costPayments, $legacyCosts, 2);

        $expensesQuery = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from, "{$to} 23:59:59"])
            ->whereIn('status', [ExpenseStatus::APPROVED]);
        $this->applyWorkOrderFilter($expensesQuery, 'workOrder', $osNumber);
        if ($branchId) {
            $expensesQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('work_order_id');
            });
        }
        $expenses = Decimal::string($expensesQuery->sum('amount'));

        $commissionsQuery = CommissionEvent::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
            ->whereIn('status', [CommissionEvent::STATUS_APPROVED, CommissionEvent::STATUS_PAID]);
        $this->applyWorkOrderFilter($commissionsQuery, 'workOrder', $osNumber);
        if ($branchId) {
            $commissionsQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('work_order_id');
            });
        }
        $commissions = Decimal::string($commissionsQuery->sum('commission_amount'));

        $itemCostsQuery = DB::table('work_order_items')
            ->join('work_orders', 'work_order_items.work_order_id', '=', 'work_orders.id')
            ->where('work_order_items.type', WorkOrderItem::TYPE_PRODUCT)
            ->whereNotNull('work_order_items.cost_price')
            ->where('work_orders.tenant_id', $tenantId)
            ->whereBetween('work_orders.completed_at', [$from, "{$to} 23:59:59"])
            ->when($osNumber, function ($query) use ($osNumber) {
                $query->where(function ($q) use ($osNumber) {
                    $q->where('work_orders.os_number', 'like', "%{$osNumber}%")
                        ->orWhere('work_orders.number', 'like', "%{$osNumber}%");
                });
            });

        if ($branchId) {
            $itemCostsQuery->where('work_orders.branch_id', $branchId);
        }

        $itemCosts = Decimal::string($itemCostsQuery->selectRaw('SUM(work_order_items.cost_price * work_order_items.quantity) as total')->value('total'));

        $totalCosts = bcadd(bcadd(bcadd($costs, $expenses, 2), $commissions, 2), $itemCosts, 2);
        $profit = bcsub($revenue, $totalCosts, 2);
        $margin = bccomp($revenue, '0', 2) > 0
            ? round((float) bcdiv(bcmul($profit, '100', 4), $revenue, 4), 1)
            : 0;

        return [
            'period' => ['from' => $from, 'to' => $to, 'os_number' => $osNumber],
            'revenue' => $revenue,
            'costs' => $costs,
            'expenses' => $expenses,
            'commissions' => $commissions,
            'item_costs' => $itemCosts,
            'total_costs' => $totalCosts,
            'profit' => $profit,
            'margin_pct' => $margin,
        ];
    }
}
