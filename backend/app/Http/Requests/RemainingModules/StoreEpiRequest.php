<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEpiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.epi.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'epi_type' => 'required|string|max:100',
            'ca_number' => 'nullable|string|max:20',
            'delivered_at' => 'required|date',
            'expiry_date' => 'nullable|date',
            'quantity' => 'integer|min:1',
        ];
    }
}
