<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFiscalInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        return [
            'number' => 'sometimes|string|max:50',
            'series' => 'sometimes|string|max:10',
            'type' => ['sometimes', 'string', Rule::in(['nfe', 'nfse', 'nfce', 'cte'])],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'processing', 'authorized', 'rejected', 'cancelled'])],
            'total' => 'sometimes|numeric|min:0',
        ];
    }
}
