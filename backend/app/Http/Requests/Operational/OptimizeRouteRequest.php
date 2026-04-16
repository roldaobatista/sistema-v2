<?php

namespace App\Http\Requests\Operational;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OptimizeRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('start_lat') && $this->input('start_lat') === '') {
            $this->merge(['start_lat' => null]);
        }
        if ($this->has('start_lng') && $this->input('start_lng') === '') {
            $this->merge(['start_lng' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_ids' => 'required|array',
            'work_order_ids.*' => Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId),
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
        ];
    }
}
