<?php

namespace App\Actions\Report;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class GenerateProductivityReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());
        $branchId = $this->branchId($filters);

        $techQuery = DB::table('time_entries')
            ->join('users', 'users.id', '=', 'time_entries.technician_id')
            ->whereBetween('time_entries.started_at', [$from, "{$to} 23:59:59"])
            ->where('time_entries.tenant_id', $tenantId)
            ->whereNull('time_entries.deleted_at');

        if ($branchId) {
            $techQuery->where('users.branch_id', $branchId);
        }

        $techStats = $techQuery
            ->select(
                'users.id',
                'users.name',
                DB::raw("SUM(CASE WHEN type = 'work' THEN duration_minutes ELSE 0 END) as work_minutes"),
                DB::raw("SUM(CASE WHEN type = 'travel' THEN duration_minutes ELSE 0 END) as travel_minutes"),
                DB::raw("SUM(CASE WHEN type = 'waiting' THEN duration_minutes ELSE 0 END) as waiting_minutes"),
                DB::raw('COUNT(DISTINCT work_order_id) as os_count')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $completedQuery = WorkOrder::where('work_orders.tenant_id', $tenantId)
            ->whereNotNull('work_orders.completed_at')
            ->whereBetween('work_orders.completed_at', [$from, "{$to} 23:59:59"])
            ->leftJoin('users', 'users.id', '=', 'work_orders.assigned_to')
            ->selectRaw('work_orders.assigned_to as assignee_id, users.name as assignee_name, COUNT(*) as count, SUM(work_orders.total) as total');

        if ($branchId) {
            $completedQuery->where('work_orders.branch_id', $branchId);
        }

        $completedByTech = $completedQuery
            ->groupBy('work_orders.assigned_to', 'users.name')
            ->get()
            ->map(fn ($row) => [
                'assignee_id' => $row->getAttribute('assignee_id'),
                'assignee' => $row->getAttribute('assignee_id') ? ['id' => $row->getAttribute('assignee_id'), 'name' => $row->getAttribute('assignee_name')] : null,
                'count' => $row->getAttribute('count'),
                'total' => $row->getAttribute('total'),
            ]);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'technicians' => $techStats,
            'completed_by_tech' => $completedByTech,
        ];
    }
}
