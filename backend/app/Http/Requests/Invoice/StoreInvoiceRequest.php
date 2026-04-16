<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['work_order_id', 'nf_number', 'due_date', 'observations', 'notes'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'nf_number' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'fiscal_status' => ['nullable', 'string', Rule::in([Invoice::FISCAL_STATUS_EMITTING, Invoice::FISCAL_STATUS_EMITTED, Invoice::FISCAL_STATUS_FAILED])],
            'fiscal_note_key' => ['nullable', 'string', 'max:255'],
            'fiscal_emitted_at' => ['nullable', 'date'],
            'fiscal_error' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'O cliente e obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
