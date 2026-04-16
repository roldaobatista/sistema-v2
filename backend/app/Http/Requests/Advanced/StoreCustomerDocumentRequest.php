<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customer.document.manage') || $this->user()->can('cadastros.customer.update');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:contract,alvara,avcb,license,other',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }
}
