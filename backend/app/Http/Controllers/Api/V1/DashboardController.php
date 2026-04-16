<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardStatsRequest;
use App\Http\Resources\CrmActivityResource;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    use ResolvesCurrentTenant;

    public function stats(DashboardStatsRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $from = $request->date('date_from') ?? now()->startOfMonth();
            $to = $request->date('date_to') ?? now()->endOfDay();

            $baseWorkOrders = WorkOrder::query()->where('work_orders.tenant_id', $tenantId);
            $completedStatus = WorkOrder::STATUS_COMPLETED;
            $cancelledStatus = WorkOrder::STATUS_CANCELLED;
            $inProgressStatus = WorkOrder::STATUS_IN_PROGRESS;
            $woCreatedBetween = [$from, $to];
            $woCompletedBetween = [$from, $to];
            $isSqlite = DB::getDriverName() === 'sqlite';
            $avgCompletionExpr = $isSqlite
                ? "AVG(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? AND completed_at IS NOT NULL AND created_at IS NOT NULL THEN ((strftime('%s', completed_at) - strftime('%s', created_at)) / 3600.0) END)"
                : 'AVG(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? AND completed_at IS NOT NULL AND created_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, completed_at) END)';

            /** @var object{open_os:int|string|null,in_progress_os:int|string|null,completed_month:int|string|null,revenue_month:float|int|string|null,sla_response_breached:int|string|null,sla_resolution_breached:int|string|null,sla_total:int|string|null,avg_completion_hours:float|int|string|null} $workOrderStats */
            $workOrderStats = (clone $baseWorkOrders)
                ->selectRaw(
                    'SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as open_os,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_os,
                     SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_month,
                     COALESCE(SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as revenue_month,
                     SUM(CASE WHEN sla_response_breached = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as sla_response_breached,
                     SUM(CASE WHEN sla_resolution_breached = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as sla_resolution_breached,
                     SUM(CASE WHEN sla_policy_id IS NOT NULL AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as sla_total,
                     '.$avgCompletionExpr.' as avg_completion_hours',
                    [
                        $completedStatus,
                        $cancelledStatus,
                        $inProgressStatus,
                        $completedStatus,
                        $woCompletedBetween[0],
                        $woCompletedBetween[1],
                        $completedStatus,
                        $woCompletedBetween[0],
                        $woCompletedBetween[1],
                        $woCreatedBetween[0],
                        $woCreatedBetween[1],
                        $woCreatedBetween[0],
                        $woCreatedBetween[1],
                        $woCreatedBetween[0],
                        $woCreatedBetween[1],
                        $completedStatus,
                        $woCompletedBetween[0],
                        $woCompletedBetween[1],
                    ]
                )
                ->first() ?? (object) [
                    'open_os' => 0,
                    'in_progress_os' => 0,
                    'completed_month' => 0,
                    'revenue_month' => 0,
                    'sla_response_breached' => 0,
                    'sla_resolution_breached' => 0,
                    'sla_total' => 0,
                    'avg_completion_hours' => 0,
                ];

            $openOs = (int) ($workOrderStats->open_os ?? 0);
            $inProgressOs = (int) ($workOrderStats->in_progress_os ?? 0);
            $completedMonth = (int) ($workOrderStats->completed_month ?? 0);
            $revenueMonth = (float) ($workOrderStats->revenue_month ?? 0);

            // ── Período anterior para TrendBadges ──
            $prevFrom = $from->copy()->subMonth()->startOfMonth();
            $prevTo = $from->copy()->subDay()->endOfDay();
            $prevWorkOrderStats = WorkOrder::query()
                ->where('work_orders.tenant_id', $tenantId)
                ->selectRaw(
                    'SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as open_os,
                     SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as completed_month,
                     COALESCE(SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN total ELSE 0 END), 0) as revenue_month',
                    [
                        $completedStatus,
                        $cancelledStatus,
                        $completedStatus,
                        $prevFrom,
                        $prevTo,
                        $completedStatus,
                        $prevFrom,
                        $prevTo,
                    ]
                )
                ->first() ?? (object) ['open_os' => 0, 'completed_month' => 0, 'revenue_month' => 0];

            $prevOpenOs = (int) ($prevWorkOrderStats->open_os ?? 0);
            $prevCompletedMonth = (int) ($prevWorkOrderStats->completed_month ?? 0);
            $prevRevenueMonth = (float) ($prevWorkOrderStats->revenue_month ?? 0);

            $pendingCommissions = CommissionEvent::query()
                ->where('tenant_id', $tenantId)
                ->where('status', CommissionEvent::STATUS_PENDING)
                ->sum('commission_amount');

            $expensesMonth = Expense::query()
                ->where('tenant_id', $tenantId)
                ->where('status', ExpenseStatus::APPROVED)
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

            /** @var Collection<int, object{id:int,number:string|null,os_number:string|null,customer_id:int|null,assigned_to:int|null,status:string|null,total:float|int|string|null,created_at:mixed,customer_name:string|null,customer_latitude:float|int|string|null,customer_longitude:float|int|string|null,assignee_name:string|null}> $recentOs */
            $recentOs = DB::table('work_orders')
                ->where('work_orders.tenant_id', $tenantId)
                ->leftJoin('customers', 'customers.id', '=', 'work_orders.customer_id')
                ->leftJoin('users as assignees', 'assignees.id', '=', 'work_orders.assigned_to')
                ->orderByDesc('work_orders.created_at')
                ->take(10)
                ->get([
                    'work_orders.id',
                    'work_orders.number',
                    'work_orders.os_number',
                    'work_orders.customer_id',
                    'work_orders.assigned_to',
                    'work_orders.status',
                    'work_orders.total',
                    'work_orders.created_at',
                    'customers.name as customer_name',
                    'customers.latitude as customer_latitude',
                    'customers.longitude as customer_longitude',
                    'assignees.name as assignee_name',
                ])
                ->map(static fn (object $row) => [
                    'id' => $row->id,
                    'number' => $row->number,
                    'os_number' => $row->os_number,
                    'customer_id' => $row->customer_id,
                    'assigned_to' => $row->assigned_to,
                    'status' => $row->status,
                    'total' => $row->total,
                    'created_at' => $row->created_at,
                    'customer' => $row->customer_id ? [
                        'id' => $row->customer_id,
                        'name' => $row->customer_name,
                        'latitude' => $row->customer_latitude,
                        'longitude' => $row->customer_longitude,
                    ] : null,
                    'assignee' => $row->assigned_to ? [
                        'id' => $row->assigned_to,
                        'name' => $row->assignee_name,
                    ] : null,
                ]);

            /** @var Collection<int, object{assigned_to:int,assignee_name:string|null,os_count:int|string,total_revenue:float|int|string|null}> $topTechStats */
            $topTechStats = DB::table('work_orders')
                ->where('work_orders.tenant_id', $tenantId)
                ->leftJoin('users as assignees', 'assignees.id', '=', 'work_orders.assigned_to')
                ->select(
                    'work_orders.assigned_to',
                    'assignees.name as assignee_name',
                    DB::raw('COUNT(*) as os_count'),
                    DB::raw('SUM(work_orders.total) as total_revenue')
                )
                ->where('work_orders.status', WorkOrder::STATUS_COMPLETED)
                ->whereBetween('work_orders.completed_at', [$from, $to])
                ->whereNotNull('work_orders.assigned_to')
                ->groupBy('work_orders.assigned_to', 'assignees.name')
                ->orderByDesc('os_count')
                ->take(5)
                ->get()
                ->map(static fn (object $r) => [
                    'assigned_to' => $r->assigned_to,
                    'assignee_id' => $r->assigned_to,
                    'assignee_name' => $r->assignee_name,
                    'assignee' => $r->assignee_name ? ['id' => $r->assigned_to, 'name' => $r->assignee_name] : null,
                    'count' => (int) $r->os_count,
                    'os_count' => (int) $r->os_count,
                    'total_revenue' => (float) ($r->total_revenue ?? 0),
                ]);
            $topTechnicians = $topTechStats;

            $now = now();
            $inSevenDays = now()->addDays(7);
            $inThirtyDays = now()->addDays(30);
            /** @var object{overdue_count:int|string|null,due_7_count:int|string|null,due_30_count:int|string|null} $equipmentStats */
            $equipmentStats = Equipment::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->selectRaw(
                    'SUM(CASE WHEN next_calibration_at < ? THEN 1 ELSE 0 END) as overdue_count,
                     SUM(CASE WHEN next_calibration_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_7_count,
                     SUM(CASE WHEN next_calibration_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as due_30_count',
                    [$now, $now, $inSevenDays, $now, $inThirtyDays]
                )
                ->first() ?? (object) [
                    'overdue_count' => 0,
                    'due_7_count' => 0,
                    'due_30_count' => 0,
                ];

            $eqOverdue = (int) ($equipmentStats->overdue_count ?? 0);
            $eqDue7 = (int) ($equipmentStats->due_7_count ?? 0);

            /** @var Collection<int, object{id:int,code:string|null,brand:string|null,model:string|null,customer_id:int|null,next_calibration_at:mixed,customer_name:string|null}> $eqAlerts */
            $eqAlerts = DB::table('equipments')
                ->where('equipments.tenant_id', $tenantId)
                ->whereNotNull('equipments.next_calibration_at')
                ->where('equipments.next_calibration_at', '<=', $inThirtyDays)
                ->where('equipments.is_active', true)
                ->where('equipments.status', '!=', 'discarded')
                ->leftJoin('customers', 'customers.id', '=', 'equipments.customer_id')
                ->orderBy('equipments.next_calibration_at')
                ->take(5)
                ->get([
                    'equipments.id',
                    'equipments.code',
                    'equipments.brand',
                    'equipments.model',
                    'equipments.customer_id',
                    'equipments.next_calibration_at',
                    'customers.name as customer_name',
                ])
                ->map(static fn (object $row) => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'brand' => $row->brand,
                    'model' => $row->model,
                    'customer_id' => $row->customer_id,
                    'next_calibration_at' => $row->next_calibration_at,
                    'customer' => $row->customer_id ? [
                        'id' => $row->customer_id,
                        'name' => $row->customer_name,
                    ] : null,
                ]);

            /** @var object{open_deals:int|string|null,won_deals_month:int|string|null,revenue_month:float|int|string|null} $crmStats */
            $crmStats = CrmDeal::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw(
                    'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_deals,
                     SUM(CASE WHEN status = ? AND won_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as won_deals_month,
                     COALESCE(SUM(CASE WHEN status = ? AND won_at BETWEEN ? AND ? THEN value ELSE 0 END), 0) as revenue_month',
                    [CrmDeal::STATUS_OPEN, CrmDeal::STATUS_WON, $from, $to, CrmDeal::STATUS_WON, $from, $to]
                )
                ->first() ?? (object) [
                    'open_deals' => 0,
                    'won_deals_month' => 0,
                    'revenue_month' => 0,
                ];

            $openDeals = (int) ($crmStats->open_deals ?? 0);
            $wonDealsMonth = (int) ($crmStats->won_deals_month ?? 0);
            $crmRevenueMonth = (float) ($crmStats->revenue_month ?? 0);

            $pendingFollowUps = CrmActivity::query()
                ->where('tenant_id', $tenantId)
                ->where('type', 'tarefa')
                ->whereNull('completed_at')
                ->where('scheduled_at', '<=', now())
                ->count();

            $avgHealthScore = (int) Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotNull('health_score')
                ->avg('health_score');

            $pendingStatuses = [
                AccountReceivable::STATUS_PENDING,
                AccountReceivable::STATUS_PARTIAL,
                AccountReceivable::STATUS_OVERDUE,
            ];
            $receivablePlaceholders = implode(',', array_fill(0, count($pendingStatuses), '?'));
            $receivableParams = [...$pendingStatuses, ...$pendingStatuses, $now];
            /** @var object{pending_total:float|int|string|null,overdue_total:float|int|string|null} $receivableStats */
            $receivableStats = AccountReceivable::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN status IN ({$receivablePlaceholders}) THEN amount - amount_paid ELSE 0 END), 0) as pending_total,
                     COALESCE(SUM(CASE WHEN status IN ({$receivablePlaceholders}) AND due_date < ? THEN amount - amount_paid ELSE 0 END), 0) as overdue_total",
                    $receivableParams
                )
                ->first() ?? (object) [
                    'pending_total' => 0,
                    'overdue_total' => 0,
                ];

            $payableStatuses = [
                AccountPayable::STATUS_PENDING,
                AccountPayable::STATUS_PARTIAL,
                AccountPayable::STATUS_OVERDUE,
            ];
            $payablePlaceholders = implode(',', array_fill(0, count($payableStatuses), '?'));
            $payableParams = [...$payableStatuses, ...$payableStatuses, $now];
            /** @var object{pending_total:float|int|string|null,overdue_total:float|int|string|null} $payableStats */
            $payableStats = AccountPayable::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN status IN ({$payablePlaceholders}) THEN amount - amount_paid ELSE 0 END), 0) as pending_total,
                     COALESCE(SUM(CASE WHEN status IN ({$payablePlaceholders}) AND due_date < ? THEN amount - amount_paid ELSE 0 END), 0) as overdue_total",
                    $payableParams
                )
                ->first() ?? (object) [
                    'pending_total' => 0,
                    'overdue_total' => 0,
                ];

            $receivablesPending = (float) ($receivableStats->pending_total ?? 0);
            $receivablesOverdue = (float) ($receivableStats->overdue_total ?? 0);
            $payablesPending = (float) ($payableStats->pending_total ?? 0);
            $payablesOverdue = (float) ($payableStats->overdue_total ?? 0);
            /** @var object{stock_low:int|string|null,stock_out:int|string|null} $stockStats */
            $stockStats = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->selectRaw(
                    'SUM(CASE WHEN stock_qty <= stock_min AND stock_qty > 0 THEN 1 ELSE 0 END) as stock_low,
                     SUM(CASE WHEN stock_qty <= 0 THEN 1 ELSE 0 END) as stock_out'
                )
                ->first() ?? (object) [
                    'stock_low' => 0,
                    'stock_out' => 0,
                ];

            $netRevenue = (float) $revenueMonth - (float) $expensesMonth;
            $slaResponseBreached = (int) ($workOrderStats->sla_response_breached ?? 0);
            $slaResolutionBreached = (int) ($workOrderStats->sla_resolution_breached ?? 0);
            $slaTotal = (int) ($workOrderStats->sla_total ?? 0);

            $sixMonthsStart = now()->subMonths(5)->startOfMonth();
            $groupExpr = $isSqlite
                ? "strftime('%Y-%m', completed_at)"
                : "DATE_FORMAT(completed_at, '%Y-%m')";

            $rawRevenue = (clone $baseWorkOrders)
                ->selectRaw("{$groupExpr} as ym, SUM(total) as total")
                ->where('status', WorkOrder::STATUS_COMPLETED)
                ->where('completed_at', '>=', $sixMonthsStart)
                ->groupByRaw($groupExpr)
                ->pluck('total', 'ym');

            $monthlyRevenue = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = now()->subMonths($i);
                $monthlyRevenue[] = [
                    'month' => $m->format('M'),
                    'total' => (float) ($rawRevenue[$m->format('Y-m')] ?? 0),
                ];
            }

            $avgCompletionHours = (int) round((float) ($workOrderStats->avg_completion_hours ?? 0));

            return ApiResponse::data([
                'open_os' => $openOs,
                'in_progress_os' => $inProgressOs,
                'completed_month' => $completedMonth,
                'revenue_month' => (float) $revenueMonth,
                'prev_open_os' => $prevOpenOs,
                'prev_completed_month' => $prevCompletedMonth,
                'prev_revenue_month' => $prevRevenueMonth,
                'pending_commissions' => (float) $pendingCommissions,
                'expenses_month' => (float) $expensesMonth,
                'recent_os' => $recentOs,
                'top_technicians' => $topTechnicians,
                'eq_overdue' => $eqOverdue,
                'eq_due_7' => max(0, $eqDue7),
                'eq_alerts' => $eqAlerts,
                'crm_open_deals' => $openDeals,
                'crm_won_month' => $wonDealsMonth,
                'crm_revenue_month' => (float) $crmRevenueMonth,
                'crm_pending_followups' => $pendingFollowUps,
                'crm_avg_health' => $avgHealthScore,
                'stock_low' => (int) ($stockStats->stock_low ?? 0),
                'stock_out' => (int) ($stockStats->stock_out ?? 0),
                'receivables_pending' => (float) $receivablesPending,
                'receivables_overdue' => (float) $receivablesOverdue,
                'payables_pending' => (float) $payablesPending,
                'payables_overdue' => (float) $payablesOverdue,
                'net_revenue' => $netRevenue,
                'sla_total' => $slaTotal,
                'sla_response_breached' => $slaResponseBreached,
                'sla_resolution_breached' => $slaResolutionBreached,
                'monthly_revenue' => $monthlyRevenue,
                'avg_completion_hours' => $avgCompletionHours,
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados inválidos.', 422, ['errors' => $this->sanitizeValidationErrors($e->errors())]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar dashboard', 500);
        }
    }

    public function teamStatus(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $techRoles = ['tecnico', 'tecnico_vendedor', 'motorista'];

            $technicians = User::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('roles', function ($q) use ($techRoles) {
                    $q->whereIn('name', $techRoles);
                })
                ->where('is_active', true)
                ->select('id', 'name', 'last_login_at')
                ->get();

            $total = $technicians->count();
            $onlineThreshold = now()->subMinutes(15);

            $onlineTechIds = $technicians
                ->filter(fn ($tech) => $tech->last_login_at && $tech->last_login_at >= $onlineThreshold)
                ->pluck('id');

            $online = $onlineTechIds->count();

            $techsWithActiveOs = $onlineTechIds->isNotEmpty()
                ? WorkOrder::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('assigned_to', $onlineTechIds)
                    ->where('status', WorkOrder::STATUS_IN_PROGRESS)
                    ->select('assigned_to')
                    ->selectRaw('MAX(CASE WHEN displacement_started_at IS NOT NULL AND displacement_arrived_at IS NULL THEN 1 ELSE 0 END) as has_displacement')
                    ->groupBy('assigned_to')
                    ->pluck('has_displacement', 'assigned_to')
                : collect();

            $inTransit = $techsWithActiveOs->filter(fn ($v) => $v)->count();
            $working = $techsWithActiveOs->filter(fn ($v) => ! $v)->count();
            $idle = $online - $techsWithActiveOs->count();

            $activeWorkOrders = WorkOrder::query()
                ->where('tenant_id', $tenantId)
                ->where('status', WorkOrder::STATUS_IN_PROGRESS)
                ->count();

            $pendingWorkOrders = WorkOrder::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [
                    WorkOrder::STATUS_OPEN,
                    WorkOrder::STATUS_AWAITING_DISPATCH,
                ])
                ->count();

            return ApiResponse::data([
                'total_technicians' => $total,
                'online' => $online,
                'in_transit' => $inTransit,
                'working' => $working,
                'idle' => $idle,
                'offline' => $total - $online,
                'active_work_orders' => $activeWorkOrders,
                'pending_work_orders' => $pendingWorkOrders,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team status failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar status da equipe', 500);
        }
    }

    public function financialSummary(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $from = $request->date('date_from') ?? now()->startOfMonth();
            $to = $request->date('date_to') ?? now()->endOfDay();

            // ── Contas a Receber ──
            $arBase = AccountReceivable::where('tenant_id', $tenantId);
            $arPending = (clone $arBase)
                ->whereIn('status', [
                    AccountReceivable::STATUS_PENDING,
                    AccountReceivable::STATUS_PARTIAL,
                    AccountReceivable::STATUS_OVERDUE,
                ])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total');
            $arOverdue = (clone $arBase)
                ->whereIn('status', [
                    AccountReceivable::STATUS_PENDING,
                    AccountReceivable::STATUS_PARTIAL,
                    AccountReceivable::STATUS_OVERDUE,
                ])
                ->where('due_date', '<', now())
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total');
            $arPaidPeriod = bcadd(
                $this->sumPaymentsForPeriod(AccountReceivable::class, $tenantId, $from, $to),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountReceivable, $tenantId, $from, $to),
                2
            );
            $arTotal = (clone $arBase)->count();

            // ── Contas a Pagar ──
            $apBase = AccountPayable::where('tenant_id', $tenantId);
            $apPending = (clone $apBase)
                ->whereIn('status', [
                    AccountPayable::STATUS_PENDING,
                    AccountPayable::STATUS_PARTIAL,
                    AccountPayable::STATUS_OVERDUE,
                ])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total');
            $apOverdue = (clone $apBase)
                ->whereIn('status', [
                    AccountPayable::STATUS_PENDING,
                    AccountPayable::STATUS_PARTIAL,
                    AccountPayable::STATUS_OVERDUE,
                ])
                ->where('due_date', '<', now())
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total');
            $apPaidPeriod = bcadd(
                $this->sumPaymentsForPeriod(AccountPayable::class, $tenantId, $from, $to),
                $this->sumLegacyPaidAmountWithoutPayments(new AccountPayable, $tenantId, $from, $to),
                2
            );
            $apTotal = (clone $apBase)->count();

            // ── Receita de OS ──
            $woBase = WorkOrder::where('tenant_id', $tenantId);
            $revenueMonth = (clone $woBase)->whereIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_DELIVERED])->whereBetween('completed_at', [$from, $to])->sum('total');

            // ── Despesas ──
            $expensesMonth = Expense::where('tenant_id', $tenantId)->where('status', ExpenseStatus::APPROVED)->whereBetween('expense_date', [$from, $to])->sum('amount');

            // ── Comissões pendentes ──
            $commissionsPending = CommissionEvent::where('tenant_id', $tenantId)->where('status', CommissionEvent::STATUS_PENDING)->sum('commission_amount');

            return ApiResponse::data([
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'receivables' => [
                    'total_count' => $arTotal,
                    'pending_amount' => (float) $arPending,
                    'overdue_amount' => (float) $arOverdue,
                    'paid_period' => (float) $arPaidPeriod,
                ],
                'payables' => [
                    'total_count' => $apTotal,
                    'pending_amount' => (float) $apPending,
                    'overdue_amount' => (float) $apOverdue,
                    'paid_period' => (float) $apPaidPeriod,
                ],
                'revenue_period' => (float) $revenueMonth,
                'expenses_period' => (float) $expensesMonth,
                'net_result' => (float) $revenueMonth - (float) $expensesMonth,
                'commissions_pending' => (float) $commissionsPending,
                'balance' => (float) $arPaidPeriod - (float) $apPaidPeriod,
            ]);
        } catch (\Throwable $e) {
            Log::error('Financial summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar resumo financeiro', 500);
        }
    }

    public function activities(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $limit = max(1, min((int) $request->integer('limit', 10), 50));

            $activities = CrmActivity::query()
                ->where('tenant_id', $tenantId)
                ->with([
                    'customer:id,name',
                    'deal:id,title',
                    'user:id,name',
                ])
                ->latest()
                ->take($limit)
                ->get();

            return ApiResponse::data(CrmActivityResource::collection($activities)->resolve());
        } catch (\Throwable $e) {
            Log::error('Dashboard activities failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar atividades do dashboard', 500);
        }
    }

    private function sumPaymentsForPeriod(string $payableType, int $tenantId, $from, $to): string
    {
        return (string) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payable_type', $payableType)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    private function sumLegacyPaidAmountWithoutPayments(AccountReceivable|AccountPayable $model, int $tenantId, $from, $to): string
    {
        return (string) $model::query()
            ->where('tenant_id', $tenantId)
            ->where('amount_paid', '>', 0)
            ->whereDoesntHave('payments')
            ->whereBetween(DB::raw('COALESCE(paid_at, due_date)'), [$from->toDateString(), $to->toDateString()])
            ->sum('amount_paid');
    }

    private function sanitizeValidationErrors(array $errors): array
    {
        $sanitize = static function (string $value): string {
            if (mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }

            return mb_convert_encoding($value, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');
        };

        return collect($errors)
            ->map(function ($messages) use ($sanitize) {
                if (! is_array($messages)) {
                    return $messages;
                }

                return array_map(function ($message) use ($sanitize) {
                    return is_string($message) ? $sanitize($message) : $message;
                }, $messages);
            })
            ->toArray();
    }
}
