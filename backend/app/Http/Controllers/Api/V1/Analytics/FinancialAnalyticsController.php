<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\ApproveBatchPaymentRequest;
use App\Http\Resources\AccountPayableResource;
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

class FinancialAnalyticsController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * GET /financial/cash-flow-projection
     * Projects cash inflows/outflows for the next N months.
     */
    public function cashFlowProjection(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();
        $months = min((int) $request->input('months', 6), 12);
        $startDate = Carbon::today();
        $projection = [];

        for ($i = 0; $i < $months; $i++) {
            $from = $startDate->copy()->addMonths($i)->startOfMonth();
            $to = $from->copy()->endOfMonth();

            $receivables = AccountReceivable::where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$from, $to])
                ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total, COUNT(*) as count')
                ->first();

            $payables = AccountPayable::where('tenant_id', $tenantId)
                ->whereBetween('due_date', [$from, $to])
                ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total, COUNT(*) as count')
                ->first();

            $inflowStr = $this->decimal($receivables?->getAttribute('total') ?? 0);
            $outflowStr = $this->decimal($payables?->getAttribute('total') ?? 0);

            $projection[] = [
                'month' => $from->format('Y-m'),
                'label' => $from->translatedFormat('M/Y'),
                'inflows' => bcadd($inflowStr, '0', 2),
                'inflows_count' => (int) ($receivables?->getAttribute('count') ?? 0),
                'outflows' => bcadd($outflowStr, '0', 2),
                'outflows_count' => (int) ($payables?->getAttribute('count') ?? 0),
                'net' => bcsub($inflowStr, $outflowStr, 2),
            ];
        }

        return ApiResponse::data($projection);
    }

    /**
     * GET /financial/dre
     * Income statement (DRE) for a given period.
     */
    public function dre(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', now()->endOfMonth()));

        // Revenue
        $revenue = bcadd(
            $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $from, $to),
            2
        );

        // COGS (custo dos produtos vendidos — usa cost_price, não unit_price/sell_price)
        $cogs = $this->decimal(WorkOrder::where('work_orders.tenant_id', $tenantId)
            ->whereNotNull('work_orders.completed_at')
            ->whereBetween('work_orders.completed_at', [$from, $to])
            ->join('work_order_items', 'work_orders.id', '=', 'work_order_items.work_order_id')
            ->where('work_order_items.type', 'product')
            ->sum(DB::raw('work_order_items.quantity * COALESCE(work_order_items.cost_price, 0)')));

        // Operating expenses
        $expenses = bcadd(
            $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $from, $to),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $from, $to),
            2
        );

        // Expense breakdown by category
        $expensesByCategory = AccountPayable::where('accounts_payable.tenant_id', $tenantId)
            ->join('payments', function ($join) {
                $join->on('payments.payable_id', '=', 'accounts_payable.id')
                    ->where('payments.payable_type', '=', AccountPayable::class);
            })
            ->whereBetween('payments.payment_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->leftJoin('account_payable_categories', 'accounts_payable.category_id', '=', 'account_payable_categories.id')
            ->select(
                DB::raw('COALESCE(account_payable_categories.name, \'Sem categoria\') as category'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as total')
            )
            ->groupBy('account_payable_categories.name')
            ->orderByDesc('total')
            ->get();

        $grossProfit = bcsub($revenue, $cogs, 2);
        $operatingProfit = bcsub($grossProfit, $expenses, 2);
        $grossMargin = bccomp($revenue, '0', 2) > 0
            ? bcmul(bcdiv($grossProfit, $revenue, 4), '100', 1)
            : '0';
        $operatingMargin = bccomp($revenue, '0', 2) > 0
            ? bcmul(bcdiv($operatingProfit, $revenue, 4), '100', 1)
            : '0';

        return ApiResponse::data([
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => (float) $grossMargin,
            'operating_expenses' => $expenses,
            'operating_profit' => $operatingProfit,
            'operating_margin' => (float) $operatingMargin,
            'expenses_by_category' => $expensesByCategory,
        ]);
    }

    /**
     * GET /financial/aging-report
     * Accounts receivable aging report (Relatório de Envelhecimento).
     */
    public function agingReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();
        $today = Carbon::today();

        $receivables = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', [
                FinancialStatus::PAID->value,
                FinancialStatus::CANCELLED->value,
                FinancialStatus::RENEGOTIATED->value,
            ])
            ->with('customer:id,name')
            ->get();

        $buckets = [
            'current' => ['label' => 'A vencer', 'total' => '0.00', 'count' => 0, 'items' => []],
            '1_30' => ['label' => '1-30 dias', 'total' => '0.00', 'count' => 0, 'items' => []],
            '31_60' => ['label' => '31-60 dias', 'total' => '0.00', 'count' => 0, 'items' => []],
            '61_90' => ['label' => '61-90 dias', 'total' => '0.00', 'count' => 0, 'items' => []],
            'over_90' => ['label' => '> 90 dias', 'total' => '0.00', 'count' => 0, 'items' => []],
        ];

        $netAmountAccessor = function (AccountReceivable $rec): string {
            return bcsub($this->decimal($rec->amount), $this->decimal($rec->amount_paid ?? 0), 2);
        };

        foreach ($receivables as $rec) {
            $dueDate = Carbon::parse($rec->due_date);
            $daysOverdue = $today->diffInDays($dueDate, false);
            $netAmount = $netAmountAccessor($rec);

            $bucket = match (true) {
                $daysOverdue >= 0 => 'current',
                $daysOverdue >= -30 => '1_30',
                $daysOverdue >= -60 => '31_60',
                $daysOverdue >= -90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucket]['total'] = bcadd($buckets[$bucket]['total'], $netAmount, 2);
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['items'][] = [
                'id' => $rec->id,
                'customer_name' => $rec->customer->name ?? '',
                'description' => $rec->description ?? '',
                'amount' => bcadd($netAmount, '0', 2),
                'due_date' => $rec->due_date?->toDateString(),
                'days_overdue' => $daysOverdue < 0 ? (int) abs($daysOverdue) : 0,
            ];
        }

        $total = '0.00';
        foreach ($buckets as $b) {
            $total = bcadd($total, $b['total'], 2);
        }

        return ApiResponse::data([
            'buckets' => $buckets,
            'total_outstanding' => $total,
            'total_overdue' => bcsub($total, $buckets['current']['total'], 2),
            'total_records' => $receivables->count(),
        ]);
    }

    /**
     * GET /financial/expense-allocation
     * Expense allocation per work order.
     */
    public function expenseAllocation(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', now()->endOfMonth()));

        $query = DB::table('expenses')
            ->where('expenses.tenant_id', $tenantId)
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->whereNotNull('expenses.work_order_id')
            ->join('work_orders', 'expenses.work_order_id', '=', 'work_orders.id')
            ->leftJoin('customers', 'work_orders.customer_id', '=', 'customers.id')
            ->select(
                'work_orders.id as work_order_id',
                'work_orders.os_number',
                'customers.name as customer_name',
                DB::raw('COUNT(expenses.id) as expense_count'),
                DB::raw('COALESCE(SUM(expenses.amount), 0) as total_expenses'),
                'work_orders.total as work_order_total'
            )
            ->groupBy('work_orders.id', 'work_orders.os_number', 'customers.name', 'work_orders.total')
            ->orderByDesc('total_expenses');

        $allocations = $query->get()->map(function ($row) {
            $woTotal = $this->decimal($row->work_order_total ?? 0);
            $expTotal = $this->decimal($row->total_expenses ?? 0);
            $margin = null;
            if (bccomp($woTotal, '0', 2) > 0) {
                $diff = bcsub($woTotal, $expTotal, 2);
                $margin = bcmul(bcdiv($diff, $woTotal, 6), '100', 1);
            }

            return [
                'work_order_id' => $row->work_order_id,
                'os_number' => $row->os_number,
                'customer_name' => $row->customer_name,
                'expense_count' => $row->expense_count,
                'total_expenses' => bcadd($expTotal, '0', 2),
                'work_order_total' => bcadd($woTotal, '0', 2),
                'net_margin' => $margin,
            ];
        });

        $totalAllocated = $allocations->reduce(fn (string $carry, array $item) => bcadd($carry, $this->decimal($item['total_expenses']), 2), '0.00');

        return ApiResponse::data([
            'data' => $allocations,
            'summary' => [
                'total_expenses_allocated' => $totalAllocated,
                'total_os_count' => $allocations->count(),
                'average_margin' => $allocations->avg('net_margin'),
            ],
        ]);
    }

    /**
     * GET /financial/batch-payment-approval
     * Lists payables pending batch approval.
     */
    public function batchPaymentApproval(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();

        $query = AccountPayable::where('tenant_id', $tenantId)
            ->where('status', FinancialStatus::PENDING->value)
            ->with(['supplierRelation:id,name']);

        if ($request->filled('due_before')) {
            $query->where('due_date', '<=', $request->input('due_before'));
        }

        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->input('min_amount'));
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $payables = $query->orderBy('due_date')->paginate($perPage);

        return ApiResponse::paginated($payables, resourceClass: AccountPayableResource::class);
    }

    /**
     * POST /financial/batch-payment-approval
     * Processes batch payment for multiple payables at once.
     */
    public function approveBatchPayment(ApproveBatchPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $validated = $request->validated();

        $tenantId = $this->tenantId();
        $paymentMethod = $validated['payment_method'] ?? 'transferencia';

        DB::beginTransaction();

        try {
            $payables = AccountPayable::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['ids'])
                ->where('status', FinancialStatus::PENDING->value)
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($payables as $payable) {
                $remaining = bcsub((string) $payable->amount, (string) $payable->amount_paid, 2);
                if (bccomp($remaining, '0', 2) <= 0) {
                    continue;
                }

                Payment::create([
                    'tenant_id' => $tenantId,
                    'payable_type' => AccountPayable::class,
                    'payable_id' => $payable->id,
                    'received_by' => auth()->id(),
                    'amount' => $remaining,
                    'payment_method' => $paymentMethod,
                    'payment_date' => now()->toDateString(),
                    'notes' => 'Pagamento em lote',
                ]);

                $count++;
            }

            DB::commit();

            return ApiResponse::message("{$count} pagamento(s) processado(s) com sucesso", 200, ['processed_count' => $count]);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao processar pagamentos em lote', 500);
        }
    }

    /**
     * GET /financial/cash-flow-weekly
     * Daily (or weekly) cash flow projection with running balance and health alerts.
     * Returns for each day: inflows, outflows, balance_projected, obligations_total, alert (shortage|tight|ok).
     */
    public function cashFlowWeekly(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $this->tenantId();
        $weeks = min(max((int) $request->input('weeks', 4), 1), 12);
        $from = Carbon::parse($request->input('from', now()->toDateString()));
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))
            : $from->copy()->addWeeks($weeks)->subDay();
        $initialBalance = $this->decimal($request->input('initial_balance', '0'));
        $marginThreshold = $this->decimal($request->input('margin_threshold', '0.15')); // 15% margin = tight

        if ($to->lt($from)) {
            $to = $from->copy()->addWeeks($weeks)->subDay();
        }

        $days = [];
        $current = $from->copy();
        while ($current->lte($to)) {
            $days[] = $current->copy()->toDateString();
            $current->addDay();
        }

        $receivablesByDate = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(due_date) as d, COALESCE(SUM(amount - amount_paid), 0) as total')
            ->groupByRaw('DATE(due_date)')
            ->get()
            ->keyBy('d');

        $payablesByDate = AccountPayable::where('tenant_id', $tenantId)
            ->whereNotIn('status', [FinancialStatus::PAID->value, FinancialStatus::CANCELLED->value, FinancialStatus::RENEGOTIATED->value])
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(due_date) as d, COALESCE(SUM(amount - amount_paid), 0) as total')
            ->groupByRaw('DATE(due_date)')
            ->get()
            ->keyBy('d');

        $expensesByDate = Expense::where('tenant_id', $tenantId)
            ->where('status', ExpenseStatus::APPROVED)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(expense_date) as d, COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('DATE(expense_date)')
            ->get()
            ->keyBy('d');

        $result = [];
        $balance = (string) $initialBalance;
        $today = Carbon::today()->toDateString();

        foreach ($days as $d) {
            $inflows = $this->decimal($receivablesByDate->get($d)?->getAttribute('total') ?? 0);
            $outflowsPay = $this->decimal($payablesByDate->get($d)?->getAttribute('total') ?? 0);
            $outflowsExp = $this->decimal($expensesByDate->get($d)?->getAttribute('total') ?? 0);
            $outflows = bcadd($outflowsPay, $outflowsExp, 2);
            $balance = bcadd($balance, bcsub($inflows, $outflows, 2), 2);

            $obligations = $outflows;
            $alert = 'ok';
            if (bccomp($obligations, '0', 2) > 0) {
                if (bccomp($balance, $obligations, 2) < 0) {
                    $alert = 'shortage';
                } elseif (bccomp($balance, '0', 2) > 0) {
                    $margin = bcdiv(bcsub($balance, $obligations, 2), $obligations, 4);
                    if (bccomp($margin, (string) $marginThreshold, 4) < 0) {
                        $alert = 'tight';
                    }
                }
            }

            $result[] = [
                'date' => $d,
                'label' => Carbon::parse($d)->format('d/m'),
                'inflows' => bcadd($inflows, '0', 2),
                'outflows' => bcadd($outflows, '0', 2),
                'obligations_total' => bcadd($obligations, '0', 2),
                'balance_projected' => $balance,
                'alert' => $alert,
                'is_today' => $d === $today,
            ];
        }

        $shortageDays = array_filter($result, fn ($r) => $r['alert'] === 'shortage');
        $tightDays = array_filter($result, fn ($r) => $r['alert'] === 'tight');

        $minBalance = null;
        $minBalanceDate = null;
        foreach ($result as $r) {
            if ($minBalance === null || bccomp($this->decimal($r['balance_projected']), $minBalance, 2) < 0) {
                $minBalance = $r['balance_projected'];
                $minBalanceDate = $r['date'];
            }
        }

        return ApiResponse::data([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'initial_balance' => bcadd($initialBalance, '0', 2),
            'days' => $result,
            'summary' => [
                'days_shortage' => count($shortageDays),
                'days_tight' => count($tightDays),
                'min_balance' => bcadd($minBalance ?? '0', '0', 2),
                'min_balance_date' => $minBalanceDate,
            ],
        ]);
    }

    /** @return numeric-string */
    private function sumPaymentsForPeriod(string $payableType, int $tenantId, Carbon $from, Carbon $to): string
    {
        return $this->decimal(Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->sum('amount'));
    }

    /** @return numeric-string */
    private function sumLegacyPaidAmountWithoutPayments(AccountReceivable|AccountPayable $model, int $tenantId, Carbon $from, Carbon $to): string
    {
        return $this->decimal($model::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->sum('amount_paid'));
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        return '0';
    }
}
