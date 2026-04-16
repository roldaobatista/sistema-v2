<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncEquipmentModelProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.equipment_model.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'product_ids' => 'required|array',
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
