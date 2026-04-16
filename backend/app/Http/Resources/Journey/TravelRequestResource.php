<?php

namespace App\Http\Resources\Journey;

use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TravelRequest
 */
class TravelRequestResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'approved_by' => $this->approved_by,
            'status' => $this->status,
            'destination' => $this->destination,
            'purpose' => $this->purpose,
            'departure_date' => $this->departure_date?->format('Y-m-d'),
            'return_date' => $this->return_date?->format('Y-m-d'),
            'departure_time' => $this->departure_time,
            'return_time' => $this->return_time,
            'estimated_days' => $this->estimated_days,
            'daily_allowance_amount' => $this->daily_allowance_amount,
            'total_advance_requested' => $this->total_advance_requested,
            'requires_vehicle' => $this->requires_vehicle,
            'fleet_vehicle_id' => $this->fleet_vehicle_id,
            'requires_overnight' => $this->requires_overnight,
            'rest_days_after' => $this->rest_days_after,
            'overtime_authorized' => $this->overtime_authorized,
            'work_orders' => $this->work_orders,
            'itinerary' => $this->itinerary,
            'meal_policy' => $this->meal_policy,
            'overnight_stays' => $this->whenLoaded('overnightStays'),
            'advances' => $this->whenLoaded('advances'),
            'expense_report' => $this->whenLoaded('expenseReport'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
