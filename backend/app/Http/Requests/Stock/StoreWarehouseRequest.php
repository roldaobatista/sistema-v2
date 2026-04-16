<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.warehouse.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }
}
