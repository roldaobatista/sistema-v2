<?php

namespace App\Http\Requests\Financial;

use App\Models\CommissionEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCommissionEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commissions.event.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'ids' => 'required|array|min:1',
            'ids.*' => ['integer', Rule::exists('commission_events', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'status' => ['required', Rule::in(array_keys(CommissionEvent::STATUSES))],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Informe ao menos um evento.',
            'status.required' => 'O status é obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
