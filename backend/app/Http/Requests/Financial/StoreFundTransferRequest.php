<?php

namespace App\Http\Requests\Financial;

use App\Http\Requests\Concerns\ResolvesTenantUserValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundTransferRequest extends FormRequest
{
    use ResolvesTenantUserValidation;

    public function authorize(): bool
    {
        return $this->user()->can('financial.fund_transfer.create');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'bank_account_id' => [
                'required',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'to_user_id' => ['required', $this->tenantUserExistsRule()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:30'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'A conta bancaria e obrigatoria.',
            'to_user_id.required' => 'O tecnico destinatario e obrigatório.',
            'amount.required' => 'O valor e obrigatório.',
            'transfer_date.required' => 'A data da transferencia e obrigatoria.',
            'payment_method.required' => 'A forma de pagamento e obrigatoria.',
            'description.required' => 'A descricao e obrigatoria.',
        ];
    }
}
