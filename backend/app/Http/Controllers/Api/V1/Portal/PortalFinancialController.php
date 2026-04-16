<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PortalFinancialController extends Controller
{
    public function index(int $customerId): JsonResponse
    {
        $tenantId = (int) (auth()->user()?->current_tenant_id ?? auth()->user()?->tenant_id);

        $receivables = AccountReceivable::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderByDesc('due_date')
            ->paginate(20);

        return ApiResponse::paginated($receivables);
    }
}
