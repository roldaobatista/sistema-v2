<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.adjustment.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['adjusted_clock_in', 'adjusted_clock_out'] as $field) {
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
            'time_clock_entry_id' => ['required', Rule::exists('time_clock_entries', 'id')->where('tenant_id', $tenantId)],
            'adjusted_clock_in' => 'nullable|date',
            'adjusted_clock_out' => 'nullable|date',
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'time_clock_entry_id.required' => 'O registro de ponto é obrigatório.',
            'reason.required' => 'O motivo do ajuste é obrigatório.',
        ];
    }
}
