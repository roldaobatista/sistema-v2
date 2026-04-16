<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolCalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.tool.update');
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('next_calibration_date') && ! $this->has('next_due_date')) {
            $normalized['next_due_date'] = $this->input('next_calibration_date');
        }

        if ($this->has('performed_by') && ! $this->has('laboratory')) {
            $normalized['laboratory'] = $this->input('performed_by');
        }

        if ($this->has('status') && ! $this->has('result')) {
            $normalized['result'] = $this->input('status');
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'tool_inventory_id' => ['nullable', Rule::exists('tool_inventories', 'id')->where('tenant_id', $tenantId)],
            'calibration_date' => 'nullable|date',
            'next_due_date' => 'nullable|date|after:calibration_date',
            'next_calibration_date' => 'nullable|date|after:calibration_date',
            'certificate_number' => 'nullable|string',
            'laboratory' => 'nullable|string',
            'performed_by' => 'nullable|string',
            'result' => 'nullable|in:approved,rejected,adjusted,conditional',
            'status' => 'nullable|in:approved,rejected,adjusted,conditional',
            'cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
