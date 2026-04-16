<?php

namespace App\Http\Requests\ServiceOps;

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
        if ($this->has('start_lat') && ! $this->has('start_latitude')) {
            $this->merge(['start_latitude' => $this->input('start_lat')]);
        }
        if ($this->has('start_lng') && ! $this->has('start_longitude')) {
            $this->merge(['start_longitude' => $this->input('start_lng')]);
        }
        if ($this->has('start_latitude') && $this->input('start_latitude') === '') {
            $this->merge(['start_latitude' => null]);
        }
        if ($this->has('start_longitude') && $this->input('start_longitude') === '') {
            $this->merge(['start_longitude' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_ids' => 'required|array|min:1',
            'work_order_ids.*' => ['integer', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
            'start_latitude' => 'nullable|numeric',
            'start_longitude' => 'nullable|numeric',
        ];
    }
}
