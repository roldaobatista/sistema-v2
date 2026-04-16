<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class BatchInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'work_order_ids' => 'required|array|min:2|max:50',
            'work_order_ids.*' => 'required|integer|exists:work_orders,id',
            'installments' => 'nullable|integer|min:1|max:24',
            'payment_notes' => 'nullable|string|max:500',
            'discount' => 'nullable|numeric|min:0',
            'discount_reason' => 'required_with:discount|string|min:10|max:500',
        ];
    }
}
