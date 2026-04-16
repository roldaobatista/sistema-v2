<?php

namespace App\Http\Resources;

use App\Models\AccountReceivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountReceivable
 */
class PortalAccountReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'status' => $status,
            'payment_method' => $this->payment_method,
            'penalty_amount' => $this->penalty_amount,
            'interest_amount' => $this->interest_amount,
            'discount_amount' => $this->discount_amount,
            'numero_documento' => $this->numero_documento,
        ];
    }
}
