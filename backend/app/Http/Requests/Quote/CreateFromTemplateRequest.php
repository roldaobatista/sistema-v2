<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateFromTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'seller_id' => 'nullable|integer',
            'equipments' => 'required|array|min:1',
        ];
    }
}
