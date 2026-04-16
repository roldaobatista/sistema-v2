<?php

namespace App\Http\Resources;

use App\Models\FuelingLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FuelingLog
 */
class FuelingLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'work_order_id' => $this->work_order_id,
            'fueling_date' => $this->fueling_date?->format('Y-m-d'),
            'vehicle_plate' => $this->vehicle_plate,
            'odometer_km' => $this->odometer_km,
            'gas_station_name' => $this->gas_station_name,
            'fuel_type' => $this->fuel_type,
            'liters' => $this->liters,
            'price_per_liter' => $this->price_per_liter,
            'total_amount' => $this->total_amount,
            'receipt_path' => $this->receipt_path,
            'notes' => $this->notes,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'affects_technician_cash' => $this->affects_technician_cash,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('user')) {
            $arr['user'] = $this->user ? $this->user->only(['id', 'name']) : null;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder ? $this->workOrder->only(['id', 'os_number']) : null;
        }
        if ($this->relationLoaded('approver')) {
            $arr['approver'] = $this->approver ? $this->approver->only(['id', 'name']) : null;
        }

        return $arr;
    }
}
