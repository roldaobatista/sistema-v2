<?php

namespace App\Http\Resources;

use App\Models\TechnicianFundRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TechnicianFundRequest
 */
class TechnicianFundRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'technician' => $this->whenLoaded('technician'),
            'approver' => $this->whenLoaded('approver'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
