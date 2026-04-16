<?php

namespace App\Http\Requests\Equipment;

use App\Models\StandardWeight;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStandardWeightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.standard_weight.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'serial_number', 'manufacturer', 'precision_class', 'material', 'shape',
            'certificate_number', 'certificate_date', 'certificate_expiry', 'certificate_file',
            'laboratory', 'status', 'notes',
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
        return [
            'nominal_value' => 'sometimes|numeric|min:0',
            'unit' => ['sometimes', Rule::in(StandardWeight::UNITS)],
            'serial_number' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:150',
            'precision_class' => ['nullable', Rule::in(array_keys(StandardWeight::PRECISION_CLASSES))],
            'material' => 'nullable|string|max:100',
            'shape' => ['nullable', Rule::in(array_keys(StandardWeight::SHAPES))],
            'certificate_number' => 'nullable|string|max:100',
            'certificate_date' => 'nullable|date',
            'certificate_expiry' => 'nullable|date|after_or_equal:certificate_date',
            'certificate_file' => 'nullable|string|max:500',
            'laboratory' => 'nullable|string|max:200',
            'status' => ['nullable', Rule::in(array_keys(StandardWeight::STATUSES))],
            'notes' => 'nullable|string',
            'laboratory_accreditation' => 'nullable|string|max:100',
            'traceability_chain' => 'nullable|string|max:500',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
