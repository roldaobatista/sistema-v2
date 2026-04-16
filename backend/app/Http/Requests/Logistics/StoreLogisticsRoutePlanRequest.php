<?php

namespace App\Http\Requests\Logistics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLogisticsRoutePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('route.plan.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'technician_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'plan_date' => 'required|date',
            'status' => 'nullable|string|in:planned,in_progress,completed,cancelled',
            'stops' => 'required|array|min:1',
            'total_distance_km' => 'nullable|numeric|min:0',
            'estimated_duration_min' => 'nullable|integer|min:0',
        ];
    }
}
