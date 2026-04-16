<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class PayrollMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.payroll.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'year_month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
