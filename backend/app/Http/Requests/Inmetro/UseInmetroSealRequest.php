<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UseInmetroSealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'work_order_id' => ['required', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }
}
