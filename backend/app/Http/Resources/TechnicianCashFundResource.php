<?php

namespace App\Http\Resources;

use App\Models\TechnicianCashFund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TechnicianCashFund
 */
class TechnicianCashFundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'balance' => $this->balance,
            'card_balance' => $this->card_balance,
            'status' => $this->status,
            'credit_limit' => $this->credit_limit,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('technician')) {
            $arr['technician'] = $this->technician ? $this->technician->only(['id', 'name']) : null;
        }

        return $arr;
    }
}
