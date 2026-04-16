<?php

namespace App\Actions\Report;

use App\Enums\ExpenseStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class GenerateFinancialReportAction extends BaseReportAction
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
        $periodExpr = $this->yearMonthExpression('due_date');
        $expensePeriodExpr = $this->yearMonthExpression('expense_date');
        $paymentPeriodExpr = $this->yearMonthExpression('payments.payment_date');
        $legacyReceivablePeriodExpr = $this->yearMonthExpression('COALESCE(ar_legacy.paid_at, ar_legacy.due_date)');
        $legacyPayablePeriodExpr = $this->yearMonthExpression('COALESCE(ap_legacy.paid_at, ap_legacy.due_date)');

        $arStatsQuery = AccountReceivable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, "{$to} 23:59:59"]);
        $this->applyWorkOrderFilter($arStatsQuery, 'workOrder', $osNumber);
        if ($branchId) {
            $arStatsQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('work_order_id');
            });
        }

        $arStats = $arStatsQuery
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(amount_paid) as total_paid'),
                DB::raw("SUM(CASE WHEN status = '".AccountReceivable::STATUS_OVERDUE."' THEN amount - amount_paid ELSE 0 END) as overdue"),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        $apStatsQuery = AccountPayable::where('tenant_id', $tenantId)
            ->whereBetween('due_date', [$from, "{$to} 23:59:59"]);
        $this->applyPayableIdentifierFilter($apStatsQuery, $osNumber);

        $apStats = $apStatsQuery
            ->select(
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(amount_paid) as total_paid'),
                DB::raw("SUM(CASE WHEN status = '".AccountPayable::STATUS_OVERDUE."' THEN amount - amount_paid ELSE 0 END) as overdue"),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        $expenseByCategoryQuery = Expense::where('expenses.tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from, "{$to} 23:59:59"])
            ->whereIn('status', [ExpenseStatus::APPROVED]);
        $this->applyWorkOrderFilter($expenseByCategoryQuery, 'workOrder', $osNumber);
        if ($branchId) {
            $expenseByCategoryQuery->where(function ($q) use ($branchId) {
                $q->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('work_order_id');
            });
        }

        $expenseByCategory = $expenseByCategoryQuery
            ->leftJoin('expense_categories', function ($join) use ($tenantId) {
                $join->on('expenses.expense_category_id', '=', 'expense_categories.id')
                    ->where('expense_categories.tenant_id', '=', $tenantId);
            })
            ->select('expense_categories.name as category', DB::raw('SUM(expenses.amount) as total'))
            ->groupBy('expense_categories.name')
            ->get();

        $monthlyFlow = DB::query()
            ->selectRaw('period, SUM(income) as income, SUM(expense) as expense, SUM(income) - SUM(expense) as balance')
            ->fromSub(function ($q) use ($from, $to, $tenantId, $expensePeriodExpr, $osNumber, $branchId) {
                $paymentPeriodExpr = $this->yearMonthExpression('payments.payment_date');
                $legacyReceivablePeriodExpr = $this->yearMonthExpression('COALESCE(ar_legacy.paid_at, ar_legacy.due_date)');
                $legacyPayablePeriodExpr = $this->yearMonthExpression('COALESCE(ap_legacy.paid_at, ap_legacy.due_date)');

                $q->selectRaw("{$paymentPeriodExpr} as period, SUM(payments.amount) as income, 0 as expense")
                    ->from('payments')
                    ->join('accounts_receivable as ar_report', 'payments.payable_id', '=', 'ar_report.id')
                    ->leftJoin('work_orders as wo_report', 'ar_report.work_order_id', '=', 'wo_report.id')
                    ->where('payments.tenant_id', $tenantId)
                    ->where('payments.payable_type', AccountReceivable::class)
                    ->where('ar_report.tenant_id', $tenantId)
                    ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"])
                    ->tap(fn ($query) => $this->applyReceivablePaymentReportFilters($query, $osNumber, $branchId))
                    ->groupByRaw($paymentPeriodExpr)
                    ->unionAll(
                        DB::query()
                            ->selectRaw("{$legacyReceivablePeriodExpr} as period, SUM(ar_legacy.amount_paid) as income, 0 as expense")
                            ->from('accounts_receivable as ar_legacy')
                            ->leftJoin('work_orders as wo_legacy', 'ar_legacy.work_order_id', '=', 'wo_legacy.id')
                            ->where('ar_legacy.tenant_id', $tenantId)
                            ->where('ar_legacy.amount_paid', '>', 0)
                            ->whereNotExists(function ($sub) {
                                $sub->selectRaw(1)
                                    ->from('payments')
                                    ->whereColumn('payments.payable_id', 'ar_legacy.id')
                                    ->where('payments.payable_type', AccountReceivable::class);
                            })
                            ->whereBetween(DB::raw('COALESCE(ar_legacy.paid_at, ar_legacy.due_date)'), [$from, "{$to} 23:59:59"])
                            ->tap(fn ($query) => $this->applyReceivablePaymentReportFilters($query, $osNumber, $branchId, 'ar_legacy', 'wo_legacy'))
                            ->groupByRaw($legacyReceivablePeriodExpr)
                    )
                    ->unionAll(
                        DB::query()
                            ->selectRaw("{$paymentPeriodExpr} as period, 0 as income, SUM(payments.amount) as expense")
                            ->from('payments')
                            ->join('accounts_payable as ap_report', 'payments.payable_id', '=', 'ap_report.id')
                            ->where('payments.tenant_id', $tenantId)
                            ->where('payments.payable_type', AccountPayable::class)
                            ->where('ap_report.tenant_id', $tenantId)
                            ->whereBetween('payments.payment_date', [$from, "{$to} 23:59:59"])
                            ->tap(fn ($query) => $this->applyPayableAliasIdentifierFilter($query, $osNumber, 'ap_report'))
                            ->groupByRaw($paymentPeriodExpr)
                    )
                    ->unionAll(
                        DB::query()
                            ->selectRaw("{$legacyPayablePeriodExpr} as period, 0 as income, SUM(ap_legacy.amount_paid) as expense")
                            ->from('accounts_payable as ap_legacy')
                            ->where('ap_legacy.tenant_id', $tenantId)
                            ->where('ap_legacy.amount_paid', '>', 0)
                            ->whereNotExists(function ($sub) {
                                $sub->selectRaw(1)
                                    ->from('payments')
                                    ->whereColumn('payments.payable_id', 'ap_legacy.id')
                                    ->where('payments.payable_type', AccountPayable::class);
                            })
                            ->whereBetween(DB::raw('COALESCE(ap_legacy.paid_at, ap_legacy.due_date)'), [$from, "{$to} 23:59:59"])
                            ->tap(fn ($query) => $this->applyPayableAliasIdentifierFilter($query, $osNumber, 'ap_legacy'))
                            ->groupByRaw($legacyPayablePeriodExpr)
                    )
                    ->unionAll(
                        DB::query()
                            ->selectRaw("{$expensePeriodExpr} as period, 0 as income, SUM(amount) as expense")
                            ->from('expenses')
                            ->where('expenses.tenant_id', $tenantId)
                            ->whereBetween('expense_date', [$from, "{$to} 23:59:59"])
                            ->whereIn('expenses.status', [ExpenseStatus::APPROVED])
                            ->when($osNumber, function ($sub) use ($osNumber) {
                                $sub->join('work_orders as wo_exp', 'expenses.work_order_id', '=', 'wo_exp.id')
                                    ->where(function ($f) use ($osNumber) {
                                        $f->where('wo_exp.os_number', 'like', "%{$osNumber}%")
                                            ->orWhere('wo_exp.number', 'like', "%{$osNumber}%");
                                    });
                            })
                            ->when($branchId && ! $osNumber, function ($sub) use ($branchId) {
                                $sub->where(function ($q) use ($branchId) {
                                    $q->whereExists(function ($sq) use ($branchId) {
                                        $sq->selectRaw(1)
                                            ->from('work_orders as wo_exp_b')
                                            ->whereColumn('wo_exp_b.id', 'expenses.work_order_id')
                                            ->where('wo_exp_b.branch_id', $branchId);
                                    })->orWhereNull('expenses.work_order_id');
                                });
                            })
                            ->groupByRaw($expensePeriodExpr)
                    );
            }, 'flows')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to, 'os_number' => $osNumber],
            'receivable' => $arStats,
            'payable' => $apStats,
            'expenses_by_category' => $expenseByCategory,
            'monthly_flow' => $monthlyFlow,
        ];
    }
}
