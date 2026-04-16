<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolCalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['next_calibration_date', 'certificate_number', 'performed_by', 'result', 'notes'] as $field) {
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
            'tool_inventory_id' => ['required', 'integer', Rule::exists('tool_inventories', 'id')->where('tenant_id', $tenantId)],
            'calibration_date' => 'required|date',
            'next_calibration_date' => 'nullable|date|after:calibration_date',
            'certificate_number' => 'nullable|string|max:100',
            'performed_by' => 'nullable|string|max:255',
            'result' => 'nullable|in:approved,rejected,adjusted,conditional',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
