<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\Equipment;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalDashboardController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Unified operational dashboard metrics (RF-01.11).
     *
     * Aggregates:
     * - Open Work Orders
     * - Technicians currently in displacement (GPS)
     * - Equipment calibration alerts (Overdue or Due in 7 days)
     * - Accounts Receivable due today
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $now = now();
        $todayStr = $now->toDateString();

        // 1. Work Order Metrics
        $woStats = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(CASE WHEN status IN (?, ?, ?) THEN 1 END) as open_count,
                COUNT(CASE WHEN status = ? THEN 1 END) as in_progress_count
            ', [
                WorkOrder::STATUS_OPEN,
                WorkOrder::STATUS_AWAITING_DISPATCH,
                'AWAITING_PARTS', // Common additional status
                WorkOrder::STATUS_IN_PROGRESS,
            ])
            ->first();
        $woOpenCount = (int) ($woStats?->getAttribute('open_count') ?? 0);
        $woInProgressCount = (int) ($woStats?->getAttribute('in_progress_count') ?? 0);

        // 2. Technicians in Displacement
        $inDisplacementCount = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkOrder::STATUS_IN_PROGRESS)
            ->whereNotNull('displacement_started_at')
            ->whereNull('displacement_arrived_at')
            ->count();

        // 3. Calibration Alerts
        $inSevenDays = $now->copy()->addDays(7);
        $calibrationStats = Equipment::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->selectRaw('
                COUNT(CASE WHEN next_calibration_at < ? THEN 1 END) as overdue_count,
                COUNT(CASE WHEN next_calibration_at BETWEEN ? AND ? THEN 1 END) as due_soon_count
            ', [$todayStr, $todayStr, $inSevenDays->toDateString()])
            ->first();
        $overdueCount = (int) ($calibrationStats?->getAttribute('overdue_count') ?? 0);
        $dueSoonCount = (int) ($calibrationStats?->getAttribute('due_soon_count') ?? 0);

        // 4. Invoices/Receivables due today
        $receivablesToday = AccountReceivable::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('due_date', $todayStr)
            ->whereIn('status', [
                AccountReceivable::STATUS_PENDING,
                AccountReceivable::STATUS_PARTIAL,
                AccountReceivable::STATUS_OVERDUE,
            ])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount - amount_paid), 0) as total_amount')
            ->first();
        $receivablesCount = (int) ($receivablesToday?->getAttribute('count') ?? 0);
        $receivablesAmount = (float) ($receivablesToday?->getAttribute('total_amount') ?? 0);

        return ApiResponse::data([
            'work_orders' => [
                'open' => $woOpenCount,
                'in_progress' => $woInProgressCount,
                'in_displacement' => $inDisplacementCount,
            ],
            'equipment' => [
                'overdue' => $overdueCount,
                'due_soon' => $dueSoonCount,
            ],
            'financial' => [
                'due_today_count' => $receivablesCount,
                'due_today_amount' => $receivablesAmount,
            ],
            'last_updated' => $now->toIso8601String(),
        ]);
    }

    /**
     * List technicians currently in displacement (real-time view).
     */
    public function activeDisplacements(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $displacements = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkOrder::STATUS_IN_PROGRESS)
            ->whereNotNull('displacement_started_at')
            ->whereNull('displacement_arrived_at')
            ->with(['assignee:id,name', 'customer:id,name,latitude,longitude'])
            ->get()
            ->map(fn (WorkOrder $wo) => [
                'work_order_id' => $wo->id,
                'work_order_number' => $wo->number ?? $wo->os_number,
                'technician' => $wo->assignee?->name,
                'customer' => $wo->customer?->name,
                'started_at' => $wo->displacement_started_at,
                'destination' => [
                    'lat' => $wo->customer?->latitude,
                    'lng' => $wo->customer?->longitude,
                ],
            ]);

        return ApiResponse::data($displacements);
    }
}
