<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRepairSealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_seals.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) $this->user()->current_tenant_id;

        return [
            'seal_ids' => 'required|array|min:1',
            'seal_ids.*' => ['required', 'integer', Rule::exists('inmetro_seals', 'id')->where('tenant_id', $tenantId)->where('status', 'available')],
            'technician_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'seal_ids.*.exists' => 'Um ou mais selos não estão disponíveis para atribuição.',
            'technician_id.exists' => 'Técnico não encontrado.',
        ];
    }
}
