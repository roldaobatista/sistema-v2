<?php

namespace App\Http\Requests\CertificateEmissionChecklist;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateEmissionChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.certificate.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'equipment_calibration_id' => [
                'required', 'integer',
                "exists:equipment_calibrations,id,tenant_id,{$tenantId}",
            ],
            'equipment_identified' => ['required', 'boolean'],
            'scope_defined' => ['required', 'boolean'],
            'critical_analysis_done' => ['required', 'boolean'],
            'procedure_defined' => ['required', 'boolean'],
            'standards_traceable' => ['required', 'boolean'],
            'raw_data_recorded' => ['required', 'boolean'],
            'uncertainty_calculated' => ['required', 'boolean'],
            'adjustment_documented' => ['required', 'boolean'],
            'no_undue_interval' => ['required', 'boolean'],
            'conformity_declaration_valid' => ['required', 'boolean'],
            'accreditation_mark_correct' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
