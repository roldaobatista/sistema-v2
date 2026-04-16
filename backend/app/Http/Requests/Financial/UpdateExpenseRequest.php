<?php

namespace App\Http\Requests\Financial;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    private const LEGACY_PAYMENT_METHODS = [
        'dinheiro',
        'pix',
        'cartao_credito',
        'cartao_debito',
        'boleto',
        'transferencia',
        'corporate_card',
        'cash',
    ];

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        if ($this->has('expense_category_id')) {
            $cleaned['expense_category_id'] = $this->expense_category_id ?: null;
        }
        if ($this->has('work_order_id')) {
            $cleaned['work_order_id'] = $this->work_order_id ?: null;
        }
        if ($this->has('chart_of_account_id')) {
            $cleaned['chart_of_account_id'] = $this->chart_of_account_id ?: null;
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.expense.update') || $this->user()->can('technicians.cashbox.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;

        return [
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('active', true))],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'chart_of_account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'description' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'expense_date' => 'sometimes|date|before_or_equal:today',
            'payment_method' => [
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $normalizedCode = trim((string) $value);
                    if (in_array($normalizedCode, self::LEGACY_PAYMENT_METHODS, true)) {
                        return;
                    }

                    $existsInRegistry = PaymentMethod::query()
                        ->where('tenant_id', $tenantId)
                        ->where('code', $normalizedCode)
                        ->where('is_active', true)
                        ->exists();

                    if (! $existsInRegistry) {
                        $fail('Forma de pagamento invalida para este tenant.');
                    }
                },
            ],
            'notes' => 'nullable|string',
            'affects_technician_cash' => 'boolean',
            'affects_net_value' => 'boolean',
            'km_quantity' => 'nullable|numeric|min:0',
            'km_rate' => 'nullable|numeric|min:0',
            'km_billed_to_client' => 'boolean',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB
        ];
    }
}
