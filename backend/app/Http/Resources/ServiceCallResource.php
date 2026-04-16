<?php

namespace App\Http\Resources;

use App\Enums\ServiceCallStatus;
use App\Models\ServiceCall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCall
 */
class ServiceCallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof ServiceCallStatus
            ? $this->status
            : ServiceCallStatus::tryFrom($this->status);

        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'call_number' => $this->call_number,
            'customer_id' => $this->customer_id,
            'quote_id' => $this->quote_id,
            'contract_id' => $this->contract_id,
            'sla_policy_id' => $this->sla_policy_id,
            'template_id' => $this->template_id,
            'technician_id' => $this->technician_id,
            'driver_id' => $this->driver_id,
            'created_by' => $this->created_by,
            'status' => $status instanceof ServiceCallStatus ? $status->value : $this->status,
            'status_label' => $status instanceof ServiceCallStatus ? $status->label() : null,
            'status_color' => $status instanceof ServiceCallStatus ? $status->color() : null,
            'priority' => $this->priority,
            'scheduled_date' => $this->scheduled_date?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'sla_due_at' => $this->sla_due_at?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'google_maps_link' => $this->google_maps_link,
            'observations' => $this->observations,
            'resolution_notes' => $this->resolution_notes,
            'reschedule_count' => $this->reschedule_count,
            'reschedule_reason' => $this->reschedule_reason,
            'reschedule_history' => $this->reschedule_history,
            'sla_breached' => $this->sla_breached,
            'sla_limit_hours' => $this->sla_limit_hours,
            'sla_remaining_minutes' => $this->sla_remaining_minutes,
            'response_time_minutes' => $this->response_time_minutes,
            'resolution_time_minutes' => $this->resolution_time_minutes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('technician')) {
            $arr['technician'] = $this->technician;
        }
        if ($this->relationLoaded('driver')) {
            $arr['driver'] = $this->driver;
        }
        if ($this->relationLoaded('equipments')) {
            $arr['equipments'] = $this->equipments;
        }
        if ($this->relationLoaded('quote')) {
            $arr['quote'] = $this->quote;
        }
        if ($this->relationLoaded('createdBy')) {
            $arr['created_by_user'] = $this->createdBy;
        }
        if ($this->relationLoaded('comments')) {
            $arr['comments'] = $this->comments;
        }
        if ($this->relationLoaded('workOrders')) {
            $arr['work_orders'] = $this->workOrders;
        }

        return $arr;
    }
}
