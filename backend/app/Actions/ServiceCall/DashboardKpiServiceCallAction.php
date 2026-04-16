<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;

class DashboardKpiServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $days = (int) ($data['days'] ?? 30);
        $since = now()->subDays($days);

        $base = ServiceCall::where('tenant_id', $tenantId);

        // Volume by day
        $volumeByDay = (clone $base)->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // MTTR (Mean Time To Resolution) in hours
        $completed = (clone $base)->where('status', ServiceCallStatus::CONVERTED_TO_OS->value)
            ->where('completed_at', '>=', $since)
            ->whereNotNull('completed_at')
            ->whereNotNull('created_at')
            ->get(['created_at', 'completed_at']);
        $mttrHours = $completed->count() > 0
            ? round($completed->avg(fn ($c) => $c->completed_at->diffInMinutes($c->created_at)) / 60, 1)
            : 0;

        // Mean time triage (open → scheduled) approximation
        $triaged = (clone $base)->where('created_at', '>=', $since)
            ->whereNotNull('scheduled_date')
            ->get(['created_at', 'scheduled_date']);
        $mtTriageHours = $triaged->count() > 0
            ? round($triaged->avg(fn ($c) => Carbon::parse($c->scheduled_date)->diffInMinutes($c->created_at)) / 60, 1)
            : 0;

        // SLA breach rate
        $totalPeriod = (clone $base)->where('created_at', '>=', $since)->count();
        $breachedPeriod = (clone $base)->where('created_at', '>=', $since)
            ->whereRaw($this->slaBreachCondition('completed_at'))
            ->count();
        $slaBreachRate = $totalPeriod > 0 ? round(($breachedPeriod / $totalPeriod) * 100, 1) : 0;

        // Top 10 recurrent customers
        $topCustStats = (clone $base)->where('created_at', '>=', $since)
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
        $custNames = Customer::whereIn('id', $topCustStats->pluck('customer_id'))
            ->pluck('name', 'id');
        $topCustomers = $topCustStats->map(fn ($row) => [
            'customer' => $custNames[$row->customer_id] ?? null,
            'total' => $row->getAttribute('total'),
        ]);

        // Reschedule rate (tolerant to missing column)
        $rescheduleRate = 0;

        try {
            $rescheduled = (clone $base)->where('created_at', '>=', $since)
                ->where('reschedule_count', '>', 0)->count();
            $rescheduleRate = $totalPeriod > 0 ? round(($rescheduled / $totalPeriod) * 100, 1) : 0;
        } catch (\Throwable) {
            // Column reschedule_count may not exist yet
        }

        // By technician
        $techStats = (clone $base)->where('created_at', '>=', $since)
            ->selectRaw('technician_id, COUNT(*) as total')
            ->groupBy('technician_id')
            ->get();
        $techNamesMap = User::whereIn('id', $techStats->pluck('technician_id')->filter())
            ->pluck('name', 'id');
        $byTechnician = $techStats->map(fn ($r) => [
            'technician' => $techNamesMap[$r->technician_id] ?? 'Sem técnico',
            'total' => $r->getAttribute('total'),
        ]);

        return ApiResponse::data([
            'mttr_hours' => $mttrHours,
            'mt_triage_hours' => $mtTriageHours,
            'sla_breach_rate' => $slaBreachRate,
            'reschedule_rate' => $rescheduleRate,
            'total_period' => $totalPeriod,
            'volume_by_day' => $volumeByDay,
            'top_customers' => $topCustomers,
            'by_technician' => $byTechnician,
        ]);
    }
}
