<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFundRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'status' => ['required', 'string', 'in:approved,rejected'],
            'payment_method' => [
                'required_if:status,approved',
                'in:cash,corporate_card',
            ],
            'bank_account_id' => [
                'required_if:status,approved',
                Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O status é obrigatório.',
            'status.in' => 'O status deve ser aprovado ou rejeitado.',
            'payment_method.required_if' => 'O método de pagamento é obrigatório para aprovação.',
            'payment_method.in' => 'Método de pagamento inválido.',
            'bank_account_id.required_if' => 'A conta bancária de origem é obrigatória para aprovação.',
            'bank_account_id.exists' => 'Conta bancária informada é inválida ou não pertence à empresa.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
