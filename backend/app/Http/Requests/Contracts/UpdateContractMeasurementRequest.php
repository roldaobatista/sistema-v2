<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.update');
    }

    public function rules(): array
    {
        return [
            'contract_id' => [
                'sometimes',
                'required',
                Rule::exists('contracts', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'period' => 'sometimes|required|string|max:10',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.accepted' => 'required_with:items|boolean',
            'total_accepted' => 'sometimes|required|numeric',
            'total_rejected' => 'sometimes|required|numeric',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
