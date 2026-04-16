<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.customer.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:2',
            'zip_code' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ];
    }
}
