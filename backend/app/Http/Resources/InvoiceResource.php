<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'work_order_id' => $this->work_order_id,
            'customer_id' => $this->customer_id,
            'created_by' => $this->created_by,
            'invoice_number' => $this->invoice_number,
            'nf_number' => $this->nf_number,
            'status' => $this->status,
            'total' => $this->total,
            'discount' => $this->discount,
            'issued_at' => $this->issued_at?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'observations' => $this->observations,
            'items' => $this->items,
            'fiscal_status' => $this->fiscal_status,
            'fiscal_note_key' => $this->fiscal_note_key,
            'fiscal_emitted_at' => $this->fiscal_emitted_at?->toIso8601String(),
            'fiscal_error' => $this->fiscal_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('fiscalNote')) {
            $arr['fiscal_note'] = $this->fiscalNote;
        }
        if ($this->relationLoaded('accountsReceivable')) {
            $arr['accounts_receivable'] = $this->accountsReceivable;
        }

        return $arr;
    }
}
