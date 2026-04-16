<?php

namespace App\Http\Requests\RepairSeal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairSealBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('repair_seals.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) $this->user()->current_tenant_id;

        return [
            'type' => ['required', Rule::in(['seal', 'seal_reparo'])],
            'batch_code' => ['required', 'string', 'max:50', Rule::unique('repair_seal_batches')->where('tenant_id', $tenantId)],
            'range_start' => 'required|string|max:30',
            'range_end' => 'required|string|max:30',
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:255',
            'received_at' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'batch_code.unique' => 'Já existe um lote com este código para o seu tenant.',
            'received_at.before_or_equal' => 'A data de recebimento não pode ser no futuro.',
        ];
    }
}
