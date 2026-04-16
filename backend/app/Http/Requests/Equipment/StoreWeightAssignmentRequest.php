<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeightAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.standard_weight.update');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['assigned_at', 'expected_return_at', 'notes'] as $field) {
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'standard_weight_id' => ['required', 'integer', Rule::exists('standard_weights', 'id')->where('tenant_id', $tenantId)],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'assigned_at' => 'nullable|date',
            'expected_return_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
