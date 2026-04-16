<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.payment.create') || $this->user()->can('finance.payable.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'O fornecedor é obrigatório.',
            'description.required' => 'A descrição é obrigatória.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
