<?php

namespace App\Http\Resources;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'expense_category_id' => $this->expense_category_id,
            'work_order_id' => $this->work_order_id,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'chart_of_account_id' => $this->chart_of_account_id,
            'description' => $this->description,
            'amount' => $this->amount,
            'km_quantity' => $this->km_quantity,
            'km_rate' => $this->km_rate,
            'km_billed_to_client' => $this->km_billed_to_client,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'receipt_path' => $this->receipt_path,
            'affects_technician_cash' => $this->affects_technician_cash,
            'affects_net_value' => $this->affects_net_value,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'status' => $this->status instanceof ExpenseStatus ? $this->status->value : $this->status,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('category')) {
            $arr['category'] = $this->category;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('approver')) {
            $arr['approver'] = $this->approver;
        }
        if ($this->relationLoaded('reviewer')) {
            $arr['reviewer'] = $this->reviewer;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('chartOfAccount')) {
            $arr['chart_of_account'] = $this->chartOfAccount;
        }

        return $arr;
    }
}
