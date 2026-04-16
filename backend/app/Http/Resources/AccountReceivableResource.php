<?php

namespace App\Http\Resources;

use App\Models\AccountReceivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountReceivable
 */
class AccountReceivableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'work_order_id' => $this->work_order_id,
            'invoice_id' => $this->invoice_id,
            'created_by' => $this->created_by,
            'chart_of_account_id' => $this->chart_of_account_id,
            'description' => $this->description,
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_at' => $this->paid_at?->format('Y-m-d'),
            'status' => $this->status,
            'quote_id' => $this->quote_id,
            'cost_center_id' => $this->cost_center_id,
            'payment_method' => $this->payment_method,
            'penalty_amount' => $this->penalty_amount,
            'interest_amount' => $this->interest_amount,
            'discount_amount' => $this->discount_amount,
            'notes' => $this->notes,
            'nosso_numero' => $this->nosso_numero,
            'numero_documento' => $this->numero_documento,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('quote')) {
            $arr['quote'] = $this->quote;
        }
        if ($this->relationLoaded('chartOfAccount')) {
            $arr['chart_of_account'] = $this->chartOfAccount;
        }
        if ($this->relationLoaded('costCenter')) {
            $arr['cost_center'] = $this->costCenter;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('payments')) {
            $arr['payments'] = $this->payments;
        }
        if ($this->relationLoaded('invoice')) {
            $arr['invoice'] = $this->invoice;
        }

        return $arr;
    }
}
