<?php

namespace App\Http\Requests\Logistics;

use Illuminate\Foundation\Http\FormRequest;

class OptimizeLogisticsRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('start_latitude') && ! $this->has('start_lat')) {
            $this->merge(['start_lat' => $this->input('start_latitude')]);
        }

        if ($this->has('start_longitude') && ! $this->has('start_lng')) {
            $this->merge(['start_lng' => $this->input('start_longitude')]);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_order_ids' => 'required|array|min:1',
            'work_order_ids.*' => 'integer',
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
        ];
    }
}
