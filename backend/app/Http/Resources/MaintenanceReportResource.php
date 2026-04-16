<?php

namespace App\Http\Resources;

use App\Models\MaintenanceReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceReport
 */
class MaintenanceReportResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'work_order_id' => $this->work_order_id,
            'equipment_id' => $this->equipment_id,
            'performed_by' => $this->performed_by,
            'approved_by' => $this->approved_by,
            'defect_found' => $this->defect_found,
            'probable_cause' => $this->probable_cause,
            'corrective_action' => $this->corrective_action,
            'parts_replaced' => $this->parts_replaced,
            'seal_status' => $this->seal_status,
            'new_seal_number' => $this->new_seal_number,
            'condition_before' => $this->condition_before,
            'condition_after' => $this->condition_after,
            'requires_calibration_after' => $this->requires_calibration_after,
            'requires_ipem_verification' => $this->requires_ipem_verification,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'work_order' => $this->whenLoaded('workOrder'),
            'equipment' => $this->whenLoaded('equipment'),
            'performer' => $this->whenLoaded('performer'),
            'approver' => $this->whenLoaded('approver'),
        ];
    }
}
