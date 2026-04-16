<?php

namespace App\Http\Resources;

use App\Models\AccountPayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountPayable
 */
class AccountPayableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'supplier_id' => $this->supplier_id,
            'category_id' => $this->category_id,
            'created_by' => $this->created_by,
            'chart_of_account_id' => $this->chart_of_account_id,
            'description' => $this->description,
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'status' => $this->status,
            'work_order_id' => $this->work_order_id,
            'cost_center_id' => $this->cost_center_id,
            'payment_method' => $this->payment_method,
            'penalty_amount' => $this->penalty_amount,
            'interest_amount' => $this->interest_amount,
            'discount_amount' => $this->discount_amount,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('supplierRelation')) {
            $arr['supplier'] = $this->supplierRelation;
        }
        if ($this->relationLoaded('categoryRelation')) {
            $arr['category'] = $this->categoryRelation;
        }
        if ($this->relationLoaded('chartOfAccount')) {
            $arr['chart_of_account'] = $this->chartOfAccount;
        }
        if ($this->relationLoaded('costCenter')) {
            $arr['cost_center'] = $this->costCenter;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('payments')) {
            $arr['payments'] = $this->payments;
        }

        return $arr;
    }
}
