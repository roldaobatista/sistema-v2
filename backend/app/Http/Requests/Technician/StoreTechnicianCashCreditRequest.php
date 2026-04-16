<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianCashCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => ['nullable', Rule::in(['cash', 'corporate_card'])],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O técnico é obrigatório.',
            'amount.required' => 'O valor é obrigatório.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'description.required' => 'A descrição é obrigatória.',
            'payment_method.required' => 'O método de pagamento é obrigatório.',
            'payment_method.in' => 'Método de pagamento inválido.',
            'bank_account_id.required' => 'A conta bancária de origem é obrigatória.',
            'bank_account_id.exists' => 'Conta bancária informada é inválida ou não pertence à empresa.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
