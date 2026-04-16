<?php

namespace App\Http\Resources;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'bank_name' => $this->bank_name,
            'agency' => $this->agency,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type,
            'pix_key' => $this->pix_key,
            'balance' => $this->balance,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }

        return $arr;
    }
}
