<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowKardexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.movement.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => $this->date_from === '' ? null : $this->date_from,
            'date_to' => $this->date_to === '' ? null : $this->date_to,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ];
    }
}
