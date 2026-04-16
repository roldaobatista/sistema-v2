<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalibrationWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'calibration_date' => 'nullable|date',
            'calibration_type' => 'nullable|string|in:initial,periodic,after_repair,special',
            'calibration_location' => 'nullable|string|max:255',
            'calibration_location_type' => 'nullable|string|in:laboratory,field,client_site',
            'verification_type' => 'nullable|string|in:initial,subsequent',
            'verification_division_e' => 'nullable|numeric|min:0',
            'precision_class' => 'nullable|string|in:I,II,III,IIII',
            'calibration_method' => 'nullable|string|in:comparison,substitution,direct',
            'temperature' => 'nullable|numeric|min:-50|max:100',
            'humidity' => 'nullable|numeric|min:0|max:100',
            'pressure' => 'nullable|numeric|min:800|max:1100',
            'standard_used' => 'nullable|string|max:500',
            'technician_notes' => 'nullable|string|max:5000',
            'received_date' => 'nullable|date',
            'issued_date' => 'nullable|date',
            'gravity_acceleration' => 'nullable|numeric',
            'decision_rule' => 'nullable|in:simple,guard_band,shared_risk',
            'uncertainty_budget' => 'nullable|array',
            'laboratory_address' => 'nullable|string|max:500',
            'scope_declaration' => 'nullable|string|max:2000',
            'mass_unit' => 'nullable|string|max:20',
            'before_adjustment_data' => 'nullable|array',
            'after_adjustment_data' => 'nullable|array',
            'conformity_declaration' => 'nullable|string|max:2000',
            'certificate_template_id' => ['nullable', 'integer', Rule::exists('certificate_templates', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
