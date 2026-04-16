<?php

namespace App\Http\Resources;

use App\Models\FiscalInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalInvoice
 */
class FiscalInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'number' => $this->number,
            'series' => $this->series,
            'type' => $this->type,
            'customer_id' => $this->customer_id,
            'work_order_id' => $this->work_order_id,
            'total' => $this->total,
            'status' => $this->status,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'xml' => $this->xml,
            'pdf_url' => $this->pdf_url,
            'customer' => $this->whenLoaded('customer'),
            'work_order' => $this->whenLoaded('workOrder'),
            'items' => $this->whenLoaded('items'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
