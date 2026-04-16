<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnRepairSealsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_seals.use');
    }

    public function rules(): array
    {
        $tenantId = (int) $this->user()->current_tenant_id;

        return [
            'seal_ids' => 'required|array|min:1',
            'seal_ids.*' => ['required', 'integer', Rule::exists('inmetro_seals', 'id')->where('tenant_id', $tenantId)->where('status', 'assigned')],
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'seal_ids.*.exists' => 'Um ou mais selos não estão atribuídos e não podem ser devolvidos.',
            'reason.required' => 'Informe o motivo da devolução.',
        ];
    }
}
