<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermissionTo('hr.payroll.manage');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
