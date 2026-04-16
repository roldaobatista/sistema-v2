<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class BiSelfServiceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.bi.view');
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|in:calibration_history,cost_analysis,compliance_status,equipment_lifecycle',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }
}
