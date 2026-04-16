<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Invoice::STATUSES))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
