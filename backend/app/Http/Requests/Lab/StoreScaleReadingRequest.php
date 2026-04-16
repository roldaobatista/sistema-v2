<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScaleReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.lab.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('work_order_id') && $this->input('work_order_id') === '') {
            $this->merge(['work_order_id' => null]);
        }
        if ($this->has('reference_weight') && $this->input('reference_weight') === '') {
            $this->merge(['reference_weight' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'scale_identifier' => 'required|string|max:50',
            'reading_value' => 'required|numeric',
            'unit' => 'required|in:kg,g,mg,t,lb',
            'reference_weight' => 'nullable|numeric',
            'interface_type' => 'required|in:rs232,usb,bluetooth,manual',
        ];
    }
}
