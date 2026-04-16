<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachWorkOrderEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'equipment_id' => ['required', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }
}
