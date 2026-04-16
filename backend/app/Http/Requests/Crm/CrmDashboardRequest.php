<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.view');
    }

    public function rules(): array
    {
        return [
            'period' => 'sometimes|string|in:month,quarter,year',
            'period_ref' => [
                'nullable',
                'string',
                'max:10',
                Rule::when($this->filled('period_ref'), ['regex:/^\d{4}-\d{2}(-\d{2})?$/']),
            ],
        ];
    }
}
