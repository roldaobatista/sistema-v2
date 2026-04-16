<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class CheckDuplicateServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $customerId = $data['customer_id'];

        $duplicates = ServiceCall::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', [ServiceCallStatus::CONVERTED_TO_OS->value, ServiceCallStatus::CANCELLED->value])
            ->where('created_at', '>=', now()->subDays(30))
            ->with('technician:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'call_number', 'status', 'priority', 'technician_id', 'scheduled_date', 'created_at']);

        return ApiResponse::data([
            'has_duplicates' => $duplicates->isNotEmpty(),
            'duplicates' => $duplicates,
        ]);
    }
}
