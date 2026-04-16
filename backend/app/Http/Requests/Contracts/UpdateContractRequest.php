<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.contract.update');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('description')) {
            $this->merge([
                'name' => Str::limit(trim((string) $this->input('description')), 255, ''),
            ]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'customer_id' => [
                'sometimes',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'number' => 'sometimes|nullable|string|max:100',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|max:50',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'value' => 'sometimes|nullable|numeric|min:0',
        ];
    }
}
