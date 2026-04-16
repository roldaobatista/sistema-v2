<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferRepairSealsRequest extends FormRequest
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
            'seal_ids.*' => ['required', 'integer', Rule::exists('inmetro_seals', 'id')->where('tenant_id', $tenantId)],
            'from_technician_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'to_technician_id' => ['required', 'integer', 'different:from_technician_id', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'to_technician_id.different' => 'O técnico de destino deve ser diferente do de origem.',
        ];
    }
}
