<?php

namespace App\Http\Requests\Procurement;

use App\Models\MaterialRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('procurement.material_request.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'reference' => 'nullable|string|max:30',
            'work_order_id' => [
                'nullable',
                Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId),
            ],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['nullable', 'string', Rule::in(array_keys(MaterialRequest::STATUSES))],
            'priority' => ['nullable', 'string', Rule::in(array_keys(MaterialRequest::PRIORITIES))],
            'justification' => 'nullable|string',
        ];
    }
}
