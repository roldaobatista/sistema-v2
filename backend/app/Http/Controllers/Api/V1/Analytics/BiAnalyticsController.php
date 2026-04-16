<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bi\CreateScheduledExportRequest;
use App\Http\Requests\Bi\PeriodComparisonRequest;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Support\ApiResponse;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BiAnalyticsController extends Controller
{
    // ─── #28 Dashboard KPIs em Tempo Real ───────────────────────

    public function realtimeKpis(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $request->user()->current_tenant_id;
        $today = Carbon::today();

        $osToday = WorkOrder::where('tenant_id', $tenantId)->whereDate('created_at', $today)->count();
        $osCompleted = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
            ->whereDate(DB::raw('COALESCE(completed_at, updated_at)'), $today)->count();
        $osOpen = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_INVOICED])->count();

        $revenueToday = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereDate('payment_date', $today)
            ->sum('amount');
        $revenueToday = bcadd(
            Decimal::string($revenueToday),
            $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $today->toDateString(), $today->toDateString()),
            2
        );

        $revenueMonth = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $revenueMonth = bcadd(
            Decimal::string($revenueMonth),
            $this->sumLegacyPaidAmountWithoutPayments(
                new AccountReceivable,
                $tenantId,
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString()
            ),
            2
        );

        $overdue = DB::table('accounts_receivable')
            ->where('tenant_id', $tenantId)->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
            ->where('due_date', '<', now())->sum(DB::raw('amount - amount_paid'));

        $nps = DB::table('nps_responses')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))->avg('score');

        $activeTechs = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('users.tenant_id', $tenantId)
            ->whereIn('roles.name', ['tecnico', 'technician'])
            ->where('users.is_active', true)->count();

        return ApiResponse::data([
            'timestamp' => now()->toIso8601String(),
            'os_today' => $osToday,
            'os_completed_today' => $osCompleted,
            'os_open' => $osOpen,
            'revenue_today' => (float) bcadd(Decimal::string($revenueToday), '0', 2),
            'revenue_month' => (float) bcadd(Decimal::string($revenueMonth), '0', 2),
            'overdue_total' => (float) bcadd(Decimal::string($overdue), '0', 2),
            'nps_30d' => $nps ? round((float) $nps, 1) : null,
            'active_technicians' => $activeTechs,
        ]);
    }

    // ─── #29 Relatório de Lucratividade por OS ─────────────────

    public function profitabilityByOS(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $request->user()->current_tenant_id;
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        $workOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('completed_at', [$from, $to])
                    ->orWhere(function ($nested) use ($from, $to) {
                        $nested->whereNull('completed_at')
                            ->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->with(['customer:id,name', 'items'])
            ->get();

        $woIds = $workOrders->pluck('id');

        $commissions = DB::table('commission_events')
            ->where('tenant_id', $tenantId)
            ->whereIn('work_order_id', $woIds)
            ->groupBy('work_order_id')
            ->selectRaw('work_order_id, SUM(commission_amount) as total')
            ->pluck('total', 'work_order_id');

        $expenseTotals = DB::table('expenses')
            ->where('tenant_id', $tenantId)
            ->whereIn('work_order_id', $woIds)
            ->groupBy('work_order_id')
            ->selectRaw('work_order_id, SUM(amount) as total')
            ->pluck('total', 'work_order_id');

        $report = $workOrders->map(function ($wo) use ($commissions, $expenseTotals) {
            $revenue = Decimal::string($wo->total);

            $partsCost = $wo->items
                ->where('type', WorkOrderItem::TYPE_PRODUCT)
                ->reduce(
                    fn (string $carry, WorkOrderItem $item) => bcadd($carry, bcmul(Decimal::string($item->cost_price), Decimal::string($item->quantity), 2), 2),
                    '0'
                );

            $laborCost = '0';

            $commissionCost = Decimal::string($commissions->get($wo->id));

            $expenses = Decimal::string($expenseTotals->get($wo->id));

            $totalCost = bcadd(bcadd(bcadd($partsCost, $laborCost, 2), $commissionCost, 2), $expenses, 2);
            $profit = bcsub($revenue, $totalCost, 2);

            return [
                'work_order_id' => $wo->id,
                'customer' => $wo->customer->name ?? "Cliente #{$wo->customer_id}",
                'status' => $wo->status,
                'revenue' => $revenue,
                'parts_cost' => $partsCost,
                'labor_cost' => $laborCost,
                'commission_cost' => $commissionCost,
                'expenses' => $expenses,
                'total_cost' => $totalCost,
                'profit' => $profit,
                'margin' => bccomp($revenue, '0', 2) > 0 ? (float) bcmul(bcdiv($profit, $revenue, 4), '100', 1) : 0,
            ];
        })->sortByDesc('profit')->values();

        $totalsRevenue = $report->reduce(fn (string $carry, $item) => bcadd($carry, Decimal::string($item['revenue']), 2), '0');
        $totalsCost = $report->reduce(fn (string $carry, $item) => bcadd($carry, Decimal::string($item['total_cost']), 2), '0');
        $totalsProfit = $report->reduce(fn (string $carry, $item) => bcadd($carry, Decimal::string($item['profit']), 2), '0');
        $totals = [
            'revenue' => $totalsRevenue,
            'total_cost' => $totalsCost,
            'profit' => $totalsProfit,
            'avg_margin' => $report->count() > 0 ? round($report->avg('margin'), 1) : 0,
        ];

        return ApiResponse::data([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'work_orders' => $report->take(100),
            'totals' => $totals,
        ]);
    }

    // ─── #30 Alertas Inteligentes de Anomalias ─────────────────

    public function anomalyDetection(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $request->user()->current_tenant_id;
        $anomalies = [];
        $currentMonth = now();
        $lastMonth = now()->copy()->subMonth();

        $currentMonthRev = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereMonth('payment_date', $currentMonth->month)
            ->whereYear('payment_date', $currentMonth->year)
            ->sum('amount');

        $lastMonthRev = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', AccountReceivable::class)
            ->whereMonth('payment_date', $lastMonth->month)
            ->whereYear('payment_date', $lastMonth->year)
            ->sum('amount');

        $currentMonthRev = (float) $currentMonthRev;
        $lastMonthRev = (float) $lastMonthRev;

        if ($lastMonthRev > 0 && $currentMonthRev < ($lastMonthRev * 0.7)) {
            $anomalies[] = [
                'type' => 'revenue_drop',
                'severity' => 'high',
                'message' => 'Receita caiu '.round((1 - $currentMonthRev / $lastMonthRev) * 100, 1).'% vs mês anterior',
                'current' => round($currentMonthRev, 2),
                'previous' => round($lastMonthRev, 2),
            ];
        }

        $currentOsRate = $this->getCompletionRate($tenantId, now()->startOfMonth(), now());
        $lastOsRate = $this->getCompletionRate($tenantId, now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        if ($lastOsRate > 0 && $currentOsRate < ($lastOsRate * 0.8)) {
            $anomalies[] = [
                'type' => 'completion_rate_drop',
                'severity' => 'medium',
                'message' => 'Taxa de conclusão de OS caiu de '.round($lastOsRate, 1).'% para '.round($currentOsRate, 1).'%',
            ];
        }

        $expenseRows = Expense::query()
            ->where('tenant_id', $tenantId)
            ->where('expense_date', '>=', now()->copy()->subMonths(3)->startOfMonth())
            ->get(['expense_date', 'amount']);
        $expenseByMonth = $expenseRows
            ->groupBy(fn (Expense $expense): string => $expense->expense_date?->format('Y-m') ?? 'sem-data')
            ->map(fn (Collection $items) => (float) $items->sum('amount'));
        $avgExpenses = (float) ($expenseByMonth->avg() ?? 0);
        $currentExpenses = (float) ($expenseByMonth->get($currentMonth->format('Y-m')) ?? 0);

        if ($avgExpenses > 0 && $currentExpenses > ($avgExpenses * 1.5)) {
            $anomalies[] = [
                'type' => 'expense_spike',
                'severity' => 'medium',
                'message' => 'Despesas '.round(($currentExpenses / $avgExpenses - 1) * 100, 1).'% acima da média',
            ];
        }

        $totalOs = WorkOrder::where('tenant_id', $tenantId)->whereMonth('created_at', now()->month)->count();
        $cancelledOs = WorkOrder::where('tenant_id', $tenantId)
            ->where('status', WorkOrder::STATUS_CANCELLED)
            ->whereMonth('created_at', now()->month)->count();

        if ($totalOs > 10 && ($cancelledOs / $totalOs) > 0.15) {
            $anomalies[] = [
                'type' => 'high_cancellation',
                'severity' => 'high',
                'message' => round(($cancelledOs / $totalOs) * 100, 1).'% das OS canceladas este mês',
            ];
        }

        return ApiResponse::data([
            'anomalies_found' => count($anomalies),
            'anomalies' => $anomalies,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    // ─── #31 Exportação de Relatórios Agendada ─────────────────

    public function scheduledExports(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $request->user()->current_tenant_id;
        $exports = DB::table('scheduled_report_exports')
            ->where('tenant_id', $tenantId)->orderByDesc('created_at')->paginate(20);

        return ApiResponse::paginated($exports);
    }

    public function createScheduledExport(CreateScheduledExportRequest $request): JsonResponse
    {
        $this->authorize('create', AccountPayable::class);

        $data = $request->validated();

        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = true;
        $data['recipients'] = json_encode($data['recipients']);
        $data['filters'] = json_encode($data['filters'] ?? []);

        $id = DB::transaction(function () use ($data) {
            return DB::table('scheduled_report_exports')->insertGetId(array_merge($data, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        });

        return ApiResponse::data(['id' => $id, 'message' => 'Scheduled export created'], 201);
    }

    public function deleteScheduledExport(Request $request, int $id): JsonResponse
    {
        $this->authorize('create', AccountPayable::class);

        DB::table('scheduled_report_exports')
            ->where('id', $id)->where('tenant_id', $request->user()->current_tenant_id)->delete();

        return ApiResponse::data(['message' => 'Excluído com sucesso']);
    }

    // ─── #32 Comparativo Período a Período ─────────────────────

    public function periodComparison(PeriodComparisonRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        $tenantId = $request->user()->current_tenant_id;
        $p1 = [$request->input('period1_from'), $request->input('period1_to')];
        $p2 = [$request->input('period2_from'), $request->input('period2_to')];

        $metrics = ['os_created', 'os_completed', 'revenue', 'expenses', 'new_customers', 'avg_ticket'];

        $period1 = $this->getPeriodMetrics($tenantId, $p1[0], $p1[1]);
        $period2 = $this->getPeriodMetrics($tenantId, $p2[0], $p2[1]);

        $comparison = [];
        foreach ($metrics as $metric) {
            $v1 = $period1[$metric] ?? 0;
            $v2 = $period2[$metric] ?? 0;
            $change = $v1 > 0 ? round((($v2 - $v1) / $v1) * 100, 1) : ($v2 > 0 ? 100 : 0);

            $comparison[$metric] = [
                'period_1' => $v1,
                'period_2' => $v2,
                'change_percent' => $change,
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            ];
        }

        return ApiResponse::data([
            'period_1' => ['from' => $p1[0], 'to' => $p1[1]],
            'period_2' => ['from' => $p2[0], 'to' => $p2[1]],
            'comparison' => $comparison,
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * @param  mixed  $to
     * @param  mixed  $from
     */
    private function getCompletionRate(int $tenantId, $from, $to): float
    {
        $total = WorkOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])->count();
        $completed = WorkOrder::where('tenant_id', $tenantId)
            ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
            ->whereBetween('completed_at', [$from, $to])->count();

        return $total > 0 ? ($completed / $total) * 100 : 0;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getPeriodMetrics(int $tenantId, string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        return [
            'os_created' => WorkOrder::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'os_completed' => WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
                ->whereBetween('completed_at', [$fromDate, $toDate])->count(),
            'revenue' => round((float) Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('payable_type', AccountReceivable::class)
                ->whereBetween('payment_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->sum('amount')
                + (float) $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $fromDate->toDateString(), $toDate->toDateString()), 2),
            'expenses' => round((float) Payment::query()
                ->where('tenant_id', $tenantId)
                ->where('payable_type', AccountPayable::class)
                ->whereBetween('payment_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->sum('amount')
                + (float) $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $fromDate->toDateString(), $toDate->toDateString()), 2),
            'new_customers' => DB::table('customers')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$fromDate, $toDate])->count(),
            'avg_ticket' => round((float) (WorkOrder::where('tenant_id', $tenantId)
                ->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED])
                ->whereBetween('completed_at', [$fromDate, $toDate])
                ->avg('total') ?? 0), 2),
        ];
    }

    /**
     * @return numeric-string
     */
    private function sumLegacyPaidAmountWithoutPayments(AccountReceivable|AccountPayable $model, int $tenantId, string $from, string $to): string
    {
        return Decimal::string($model::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereNotIn('status', ['cancelled', 'renegotiated'])
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from, $to])
            ->sum('amount_paid'));
    }
}
