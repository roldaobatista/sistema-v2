<?php

namespace App\Http\Requests\Financial;

use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financeiro.payment.create') || $this->user()->can('finance.payable.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('notes') && $this->input('notes') === '') {
            $cleaned['notes'] = null;
        }
        if ($this->filled('payment_frequency')) {
            $cleaned['payment_frequency'] = LookupValueResolver::canonicalValue(
                SupplierContractPaymentFrequency::class,
                [
                    'monthly' => 'Mensal',
                    'quarterly' => 'Trimestral',
                    'annual' => 'Anual',
                    'one_time' => 'Unico',
                ],
                $this->tenantId(),
                $this->input('payment_frequency')
            );
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $allowedFrequencies = LookupValueResolver::allowedValues(
            SupplierContractPaymentFrequency::class,
            [
                'monthly' => 'Mensal',
                'quarterly' => 'Trimestral',
                'annual' => 'Anual',
                'one_time' => 'Unico',
            ],
            $tenantId
        );

        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'description' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'value' => 'required|numeric|min:0',
            'payment_frequency' => ['required', 'string', Rule::in($allowedFrequencies)],
            'auto_renew' => 'boolean',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,expired,cancelled',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
