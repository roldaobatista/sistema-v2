<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.supplier.update');
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|required|string|in:PF,PJ',
            'name' => 'sometimes|required|string|max:255',
            'document' => 'nullable|string|max:20',
            'trade_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'address_zip' => 'nullable|string|max:10',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_complement' => 'nullable|string|max:100',
            'address_neighborhood' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
