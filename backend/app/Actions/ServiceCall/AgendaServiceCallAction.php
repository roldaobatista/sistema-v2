<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;

class AgendaServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = ServiceCall::with(['customer:id,name', 'technician:id,name', 'driver:id,name', 'equipments'])
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [ServiceCallStatus::CANCELLED->value]);

        if ($techId = ($data['technician_id'] ?? null)) {
            $query->where('technician_id', $techId);
        }

        $start = ($data['start'] ?? ($data['date_from'] ?? null));
        $end = ($data['end'] ?? ($data['date_to'] ?? null));

        if ($start) {
            $query->where('scheduled_date', '>=', $start);
        }
        if ($end) {
            $query->where('scheduled_date', '<=', $end);
        }

        $calls = $query->orderBy('scheduled_date')->get();

        return ApiResponse::data(
            $calls->map(function (ServiceCall $call) {
                $scheduledTime = null;
                if ($call->scheduled_date) {
                    $scheduledTime = Carbon::parse($call->scheduled_date)->format('H:i');
                }

                return [
                    ...$call->toArray(),
                    'scheduled_time' => $scheduledTime,
                ];
            })
        );
    }
}
