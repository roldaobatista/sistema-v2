<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PortalExecutiveDashboardController extends Controller
{
    public function show(int $customerId): JsonResponse
    {
        $tenantId = (int) (auth()->user()?->current_tenant_id ?? auth()->user()?->tenant_id);

        // Financial stats — open invoices = sum of (amount - amount_paid) for non-paid/non-cancelled/non-renegotiated
        $openInvoices = AccountReceivable::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['paid', 'cancelled', 'renegotiated'])
            ->sum(DB::raw('amount - amount_paid'));

        // Work order stats
        $pendingStatuses = [
            WorkOrder::STATUS_OPEN,
            WorkOrder::STATUS_AWAITING_DISPATCH,
            WorkOrder::STATUS_IN_DISPLACEMENT,
            WorkOrder::STATUS_AT_CLIENT,
            WorkOrder::STATUS_IN_SERVICE,
            WorkOrder::STATUS_IN_PROGRESS,
            WorkOrder::STATUS_AWAITING_RETURN,
            WorkOrder::STATUS_IN_RETURN,
            WorkOrder::STATUS_SERVICE_PAUSED,
            WorkOrder::STATUS_RETURN_PAUSED,
            WorkOrder::STATUS_WAITING_PARTS,
            WorkOrder::STATUS_WAITING_APPROVAL,
        ];

        $completedStatuses = [
            WorkOrder::STATUS_COMPLETED,
            WorkOrder::STATUS_DELIVERED,
            WorkOrder::STATUS_INVOICED,
        ];

        $osPending = WorkOrder::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', $pendingStatuses)
            ->count();

        $osCompleted = WorkOrder::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', $completedStatuses)
            ->count();

        $totalOs = WorkOrder::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        return ApiResponse::data([
            'stats' => [
                'open_invoices' => (float) $openInvoices,
                'os_pending' => (int) $osPending,
                'os_completed' => (int) $osCompleted,
                'total_os' => (int) $totalOs,
            ],
        ]);
    }
}
