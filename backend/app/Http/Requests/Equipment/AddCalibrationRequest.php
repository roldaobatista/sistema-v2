<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.equipment.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'laboratory', 'certificate_number', 'certificate_pdf_path', 'standard_used',
            'uncertainty', 'error_found', 'technician_notes', 'temperature', 'humidity', 'pressure',
            'corrections_applied', 'cost', 'work_order_id', 'notes',
        ];
        $cleaned = [];
        foreach ($nullable as $field) {
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
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;

        return [
            'calibration_date' => 'required|date',
            'calibration_type' => 'required|string|max:30',
            'result' => 'required|string|max:30',
            'laboratory' => 'nullable|string|max:150',
            'certificate_number' => 'nullable|string|max:50',
            'certificate_pdf_path' => 'nullable|string|max:255',
            'standard_used' => 'nullable|string|max:255',
            'standard_weight_ids' => 'nullable|array',
            'standard_weight_ids.*' => Rule::exists('standard_weights', 'id')->where('tenant_id', $tenantId),
            'uncertainty' => 'nullable|numeric',
            'error_found' => 'nullable|numeric',
            'errors_found' => 'nullable|array',
            'technician_notes' => 'nullable|string',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
            'pressure' => 'nullable|numeric',
            'corrections_applied' => 'nullable|string',
            'cost' => 'nullable|numeric',
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'notes' => 'nullable|string',
        ];
    }
}
