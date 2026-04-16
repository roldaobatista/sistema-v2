<?php

namespace App\Actions\Report;

use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use Illuminate\Support\Facades\DB;

class GenerateEquipmentsReportAction extends BaseReportAction
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

        $applyBranchFilter = function ($query) use ($branchId) {
            if ($branchId) {
                $query->whereHas('responsible', fn ($q) => $q->where('branch_id', $branchId));
            }
        };

        $totalActiveQuery = Equipment::where('tenant_id', $tenantId)->active();
        $applyBranchFilter($totalActiveQuery);
        $totalActive = $totalActiveQuery->count();

        $totalInactiveQuery = Equipment::where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('is_active', false)->orWhere('status', Equipment::STATUS_DISCARDED);
            });
        $applyBranchFilter($totalInactiveQuery);
        $totalInactive = $totalInactiveQuery->count();

        $byClassQuery = Equipment::where('tenant_id', $tenantId)->active();
        $applyBranchFilter($byClassQuery);
        $byClass = $byClassQuery
            ->select('precision_class', DB::raw('COUNT(*) as count'))
            ->groupBy('precision_class')
            ->get();

        $overdueQuery = Equipment::where('tenant_id', $tenantId)->overdue()->active();
        $applyBranchFilter($overdueQuery);
        $overdue = $overdueQuery->count();

        $rawDue7Query = Equipment::where('tenant_id', $tenantId)->calibrationDue(7)->active();
        $applyBranchFilter($rawDue7Query);
        $rawDue7 = $rawDue7Query->count();
        $dueNext7 = max(0, $rawDue7 - $overdue);

        $rawDue30Query = Equipment::where('tenant_id', $tenantId)->calibrationDue(30)->active();
        $applyBranchFilter($rawDue30Query);
        $rawDue30 = $rawDue30Query->count();
        $dueNext30 = max(0, $rawDue30 - $overdue - $dueNext7);

        $calibrationsInPeriodQuery = EquipmentCalibration::where('equipment_calibrations.tenant_id', $tenantId)
            ->whereBetween('calibration_date', [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $calibrationsInPeriodQuery->whereHas('equipment.responsible', fn ($q) => $q->where('branch_id', $branchId));
        }
        $calibrationsInPeriod = $calibrationsInPeriodQuery
            ->select('result', DB::raw('COUNT(*) as count'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('result')
            ->get();

        $totalCalibrationCostQuery = EquipmentCalibration::where('equipment_calibrations.tenant_id', $tenantId)
            ->whereBetween('calibration_date', [$from, "{$to} 23:59:59"]);
        if ($branchId) {
            $totalCalibrationCostQuery->whereHas('equipment.responsible', fn ($q) => $q->where('branch_id', $branchId));
        }
        $totalCalibrationCost = (float) $totalCalibrationCostQuery->sum('cost');

        $topBrandsQuery = Equipment::where('tenant_id', $tenantId)->active();
        $applyBranchFilter($topBrandsQuery);
        $topBrands = $topBrandsQuery
            ->select('brand', DB::raw('COUNT(*) as count'))
            ->groupBy('brand')
            ->orderByDesc('count')
            ->take(10)
            ->get();

        $dueAlertsQuery = Equipment::where('tenant_id', $tenantId)
            ->active()
            ->whereNotNull('next_calibration_at')
            ->whereBetween('next_calibration_at', [now()->toDateString(), now()->addDays(30)->toDateString()]);
        $applyBranchFilter($dueAlertsQuery);
        $dueAlerts = $dueAlertsQuery
            ->orderBy('next_calibration_at')
            ->select('id', 'brand', 'model', 'code', 'next_calibration_at')
            ->limit(30)
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'total_active' => $totalActive,
            'total_inactive' => $totalInactive,
            'by_class' => $byClass,
            'calibration_overdue' => $overdue,
            'overdue_calibrations' => $overdue,
            'calibration_due_7' => max(0, $dueNext7),
            'calibration_due_30' => max(0, $dueNext30),
            'calibrations_period' => $calibrationsInPeriod,
            'calibrations_month' => $calibrationsInPeriod,
            'total_calibration_cost' => $totalCalibrationCost,
            'top_brands' => $topBrands,
            'due_alerts' => $dueAlerts,
        ];
    }
}
