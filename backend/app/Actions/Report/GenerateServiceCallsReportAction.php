<?php

namespace App\Actions\Report;

use App\Models\ServiceCall;
use Illuminate\Support\Facades\DB;

class GenerateServiceCallsReportAction extends BaseReportAction
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

        $branchFilter = fn ($q) => $branchId
            ? $q->whereExists(function ($sub) use ($branchId) {
                $sub->selectRaw(1)
                    ->from('users')
                    ->whereColumn('users.id', 'service_calls.technician_id')
                    ->where('users.branch_id', $branchId);
            })
            : $q;

        $byStatus = $branchFilter(
            DB::table('service_calls')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
        )->groupBy('status')->get();

        $byPriority = $branchFilter(
            DB::table('service_calls')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->select('priority', DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
        )->groupBy('priority')->get();

        $byTechnician = DB::table('service_calls')
            ->where('service_calls.tenant_id', $tenantId)
            ->whereNull('service_calls.deleted_at')
            ->leftJoin('users', 'users.id', '=', 'service_calls.technician_id')
            ->whereBetween('service_calls.created_at', [$from, "{$to} 23:59:59"])
            ->when($branchId, fn ($q) => $q->where('users.branch_id', $branchId))
            ->select('users.id', DB::raw("COALESCE(users.name, 'Sem tecnico') as name"), DB::raw('COUNT(*) as count'))
            ->groupBy('users.id', 'users.name')
            ->get();

        $total = $branchFilter(
            ServiceCall::where('tenant_id', $tenantId)->whereBetween('created_at', [$from, "{$to} 23:59:59"])
        )->count();

        $completed = $branchFilter(
            ServiceCall::where('tenant_id', $tenantId)
                ->whereIn('status', [ServiceCall::STATUS_CONVERTED_TO_OS])
                ->whereBetween('created_at', [$from, "{$to} 23:59:59"])
        )->count();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_technician' => $byTechnician,
            'total' => $total,
            'completed' => $completed,
        ];
    }
}
