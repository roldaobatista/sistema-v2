<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignWeightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'standard_weight_id' => ['required', Rule::exists('standard_weights', 'id')->where('tenant_id', $tenantId)],
            'assigned_to_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'assigned_to_vehicle_id' => ['nullable', Rule::exists('fleet_vehicles', 'id')->where('tenant_id', $tenantId)],
            'assignment_type' => 'nullable|in:field,storage,calibration_lab',
            'notes' => 'nullable|string',
        ];
    }
}
