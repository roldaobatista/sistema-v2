<?php

namespace App\Http\Requests\MaintenanceReport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.maintenance_report.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'defect_found' => ['sometimes', 'required', 'string', 'max:5000'],
            'probable_cause' => ['nullable', 'string', 'max:5000'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'parts_replaced' => ['nullable', 'array'],
            'parts_replaced.*.name' => ['required_with:parts_replaced', 'string', 'max:200'],
            'parts_replaced.*.part_number' => ['nullable', 'string', 'max:100'],
            'parts_replaced.*.origin' => ['nullable', 'string', 'max:100'],
            'parts_replaced.*.quantity' => ['nullable', 'integer', 'min:1'],
            'seal_status' => ['nullable', 'string', 'in:intact,broken,replaced,not_applicable'],
            'new_seal_number' => ['nullable', 'string', 'max:50', 'required_if:seal_status,replaced'],
            'condition_before' => ['sometimes', 'required', 'string', 'in:defective,degraded,functional,unknown'],
            'condition_after' => ['sometimes', 'required', 'string', 'in:functional,limited,requires_calibration,not_repaired'],
            'requires_calibration_after' => ['boolean'],
            'requires_ipem_verification' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ];
    }
}
