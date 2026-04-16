<?php

namespace App\Http\Resources;

use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payslip
 */
class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'payroll_line_id' => $this->payroll_line_id,
            'user_id' => $this->user_id,
            'reference_month' => $this->reference_month,
            'file_path' => $this->file_path,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'viewed_at' => $this->viewed_at?->toIso8601String(),
            'digital_signature_hash' => $this->digital_signature_hash,
            'payroll_line' => $this->whenLoaded('payrollLine'),
            'user' => $this->whenLoaded('user'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
