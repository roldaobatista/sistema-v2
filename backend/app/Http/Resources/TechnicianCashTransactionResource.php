<?php

namespace App\Http\Resources;

use App\Models\TechnicianCashTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TechnicianCashTransaction
 */
class TechnicianCashTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'fund_id' => $this->fund_id,
            'type' => $this->type,
            'payment_method' => $this->payment_method,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'expense_id' => $this->expense_id,
            'work_order_id' => $this->work_order_id,
            'created_by' => $this->created_by,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'fund' => $this->whenLoaded('fund'),
            'expense' => $this->whenLoaded('expense'),
            'work_order' => $this->whenLoaded('workOrder'),
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
