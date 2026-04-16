<?php

namespace App\Http\Resources;

use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payroll
 */
class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'reference_month' => $this->reference_month,
            'type' => $this->type,
            'status' => $this->status,
            'total_gross' => $this->total_gross,
            'total_deductions' => $this->total_deductions,
            'total_net' => $this->total_net,
            'total_fgts' => $this->total_fgts,
            'total_inss_employer' => $this->total_inss_employer,
            'employee_count' => $this->employee_count,
            'calculated_by' => $this->calculated_by,
            'approved_by' => $this->approved_by,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines'),
            'calculated_by_user' => $this->whenLoaded('calculatedBy'),
            'approved_by_user' => $this->whenLoaded('approvedBy'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
