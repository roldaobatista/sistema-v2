<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRescissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.payroll.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'type' => ['required', Rule::in(['sem_justa_causa', 'pedido_demissao', 'justa_causa', 'acordo_mutuo'])],
            'notice_type' => ['required', Rule::in(['worked', 'indemnified', 'waived'])],
            'termination_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'Colaborador não encontrado ou inativo neste tenant.',
            'termination_date.after_or_equal' => 'A data de desligamento não pode ser no passado.',
        ];
    }
}
