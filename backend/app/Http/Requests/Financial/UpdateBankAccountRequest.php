<?php

namespace App\Http\Requests\Financial;

use App\Models\BankAccount;
use App\Models\Lookups\BankAccountType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('financial.bank_account.update');
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
            'name' => ['sometimes', 'string', 'max:255'],
            'bank_name' => ['sometimes', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:20'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'account_type' => ['sometimes', Rule::in($allowedAccountTypes)],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
