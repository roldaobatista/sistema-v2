<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitNfseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'service_description' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'iss_rate' => 'nullable|numeric|min:0|max:10',
        ];
    }
}
