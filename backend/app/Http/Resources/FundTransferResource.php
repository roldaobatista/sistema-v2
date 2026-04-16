<?php

namespace App\Http\Resources;

use App\Models\FundTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FundTransfer
 */
class FundTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'bank_account_id' => $this->bank_account_id,
            'to_user_id' => $this->to_user_id,
            'amount' => $this->amount,
            'transfer_date' => $this->transfer_date?->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'description' => $this->description,
            'account_payable_id' => $this->account_payable_id,
            'technician_cash_transaction_id' => $this->technician_cash_transaction_id,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('bankAccount')) {
            $arr['bank_account'] = $this->bankAccount;
        }
        if ($this->relationLoaded('technician')) {
            $arr['technician'] = $this->technician;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('accountPayable')) {
            $arr['account_payable'] = $this->accountPayable;
        }
        if ($this->relationLoaded('cashTransaction')) {
            $arr['cash_transaction'] = $this->cashTransaction;
        }

        return $arr;
    }
}
