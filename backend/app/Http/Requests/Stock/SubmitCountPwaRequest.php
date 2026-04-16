<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCountPwaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.create');
    }

    public function rules(): array
    {
        $tenantId = (int) (app('current_tenant_id') ?? $this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.counted_quantity' => 'required|numeric|min:0',
        ];
    }
}
