<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCalibrationWeightsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'weight_ids' => 'required|array',
            'weight_ids.*' => Rule::exists('standard_weights', 'id')->where('tenant_id', $tenantId),
        ];
    }
}
