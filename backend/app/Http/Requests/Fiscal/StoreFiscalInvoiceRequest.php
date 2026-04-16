<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFiscalInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'total' => $this->input('total', $this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'number' => 'nullable|string|max:50',
            'series' => 'nullable|string|max:10',
            'type' => ['nullable', 'string', Rule::in(['nfe', 'nfse', 'nfce', 'cte'])],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'total' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'O tipo de nota fiscal é inválido.',
            'customer_id.exists' => 'O cliente informado não pertence a esta empresa.',
            'total.numeric' => 'O valor total informado é inválido.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
