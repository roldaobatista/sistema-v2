<?php

namespace App\Http\Requests\Contracts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('contracts.contract.create');
    }

    public function rules(): array
    {
        $isAdvancedRoute = $this->route('contract') || $this->route('contractId');

        $rules = [
            'period' => 'required|string|max:10',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|numeric|min:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.accepted' => 'required_with:items|boolean',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ];

        if ($isAdvancedRoute) {
            // Advanced route: contract_id comes from URL, totals computed from items
            $rules['items'] = 'required|array|min:1';
        } else {
            // Basic CRUD route: contract_id and totals are in body
            $rules['contract_id'] = [
                'required',
                Rule::exists('contracts', 'id')->where('tenant_id', $this->user()->current_tenant_id),
            ];
            $rules['items'] = 'nullable|array';
            $rules['total_accepted'] = 'required|numeric';
            $rules['total_rejected'] = 'required|numeric';
        }

        return $rules;
    }
}
