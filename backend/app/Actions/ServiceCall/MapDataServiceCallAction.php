<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class MapDataServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $query = ServiceCall::with([
            'customer:id,name,phone',
            'technician:id,name',
        ])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($status = ($data['status'] ?? null)) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [ServiceCallStatus::PENDING_SCHEDULING->value, ServiceCallStatus::SCHEDULED->value, ServiceCallStatus::RESCHEDULED->value, ServiceCallStatus::AWAITING_CONFIRMATION->value]);
        }

        $calls = $query->orderByDesc('scheduled_date')->get([
            'id',
            'call_number',
            'customer_id',
            'technician_id',
            'status',
            'priority',
            'latitude',
            'longitude',
            'city',
            'state',
            'observations',
            'scheduled_date',
            'created_at',
        ]);

        return ApiResponse::data(
            $calls->map(function (ServiceCall $call) {
                return [
                    'id' => $call->id,
                    'call_number' => $call->call_number,
                    'status' => $call->status,
                    'priority' => $call->priority,
                    'description' => $call->observations,
                    'latitude' => $call->latitude,
                    'longitude' => $call->longitude,
                    'city' => $call->city,
                    'state' => $call->state,
                    'scheduled_date' => $call->scheduled_date,
                    'created_at' => $call->created_at,
                    'customer' => $call->customer ? [
                        'id' => $call->customer->id,
                        'name' => $call->customer->name,
                        'phone' => $call->customer->phone,
                    ] : null,
                    'technician' => $call->technician ? [
                        'id' => $call->technician->id,
                        'name' => $call->technician->name,
                    ] : null,
                ];
            })
        );
    }
}
