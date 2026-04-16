<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTollTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fleet.toll.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId)],
            'toll_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:tag,manual,invoice',
            'transaction_at' => 'required|date',
            'route' => 'nullable|string|max:255',
        ];
    }
}
