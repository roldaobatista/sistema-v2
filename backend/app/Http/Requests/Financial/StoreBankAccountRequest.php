<?php

namespace App\Http\Requests\Financial;

use App\Models\BankAccount;
use App\Models\Lookups\BankAccountType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financial.bank_account.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['agency', 'account_number', 'pix_key'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        if (! $this->filled('name') && $this->filled('bank_name')) {
            $cleaned['name'] = (string) $this->input('bank_name');
        }

        if (! $this->has('balance') && $this->has('initial_balance')) {
            $cleaned['balance'] = $this->input('initial_balance');
            $cleaned['initial_balance'] = $this->input('initial_balance');
        }

        $accountType = (string) $this->input('account_type', '');
        $legacyAccountTypes = [
            'checking' => BankAccount::TYPE_CORRENTE,
            'current' => BankAccount::TYPE_CORRENTE,
            'savings' => BankAccount::TYPE_POUPANCA,
            'payment' => BankAccount::TYPE_PAGAMENTO,
        ];

        if (isset($legacyAccountTypes[$accountType])) {
            $cleaned['account_type'] = $legacyAccountTypes[$accountType];
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $allowedAccountTypes = LookupValueResolver::allowedValues(
            BankAccountType::class,
            BankAccount::ACCOUNT_TYPES,
            $tenantId
        );

        return [
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'account_type' => ['required', Rule::in($allowedAccountTypes)],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da conta e obrigatório.',
            'bank_name.required' => 'O nome do banco e obrigatório.',
            'account_type.required' => 'O tipo da conta e obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
