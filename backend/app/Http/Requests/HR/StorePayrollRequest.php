<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.payroll.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'reference_month' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
                Rule::unique('payrolls')
                    ->where('tenant_id', $tenantId)
                    ->where('type', $this->input('type', 'regular')),
            ],
            'type' => ['required', Rule::in(['regular', 'thirteenth_first', 'thirteenth_second', 'vacation', 'rescission', 'advance'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_month.unique' => 'Já existe uma folha de pagamento para este mês e tipo.',
            'reference_month.regex' => 'Formato do mês de referência deve ser YYYY-MM.',
        ];
    }
}
