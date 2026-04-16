<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'received_by' => $this->received_by,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('receiver')) {
            $arr['receiver'] = $this->receiver;
        }
        if (isset($this->payable_summary)) {
            $arr['payable_summary'] = $this->payable_summary;
        }

        return $arr;
    }
}
