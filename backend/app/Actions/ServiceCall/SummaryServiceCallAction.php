<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class SummaryServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(User $user, int $tenantId)
    {

        $base = ServiceCall::where('tenant_id', $tenantId);

        $activeBreached = (clone $base)
            ->whereNotIn('status', [ServiceCallStatus::CONVERTED_TO_OS->value, ServiceCallStatus::CANCELLED->value])
            ->whereRaw($this->slaBreachCondition('NULL'))
            ->count();

        return ApiResponse::data([
            'pending_scheduling' => (clone $base)->where('status', ServiceCallStatus::PENDING_SCHEDULING->value)->count(),
            'scheduled' => (clone $base)->where('status', ServiceCallStatus::SCHEDULED->value)->count(),
            'rescheduled' => (clone $base)->where('status', ServiceCallStatus::RESCHEDULED->value)->count(),
            'awaiting_confirmation' => (clone $base)->where('status', ServiceCallStatus::AWAITING_CONFIRMATION->value)->count(),
            'converted_today' => (clone $base)->where('status', ServiceCallStatus::CONVERTED_TO_OS->value)->whereDate('completed_at', today())->count(),
            'sla_breached_active' => $activeBreached,
        ]);
    }
}
