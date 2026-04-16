<?php

namespace App\Http\Requests\Technician;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianCashDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('technicians.cashbox.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('work_order_id') && $this->input('work_order_id') === '') {
            $this->merge(['work_order_id' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'payment_method' => ['sometimes', Rule::in(['cash', 'corporate_card'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O técnico é obrigatório.',
            'amount.required' => 'O valor é obrigatório.',
            'description.required' => 'A descrição é obrigatória.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
