<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['nf_number', 'due_date', 'observations', 'notes'];
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
        return [
            'nf_number' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(array_keys(Invoice::STATUSES))],
            'due_date' => ['nullable', 'date'],
            'observations' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'fiscal_status' => ['nullable', 'string', Rule::in([Invoice::FISCAL_STATUS_EMITTING, Invoice::FISCAL_STATUS_EMITTED, Invoice::FISCAL_STATUS_FAILED])],
            'fiscal_note_key' => ['nullable', 'string', 'max:255'],
            'fiscal_emitted_at' => ['nullable', 'date'],
            'fiscal_error' => ['nullable', 'string'],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
