<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class AccountingReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.reports.view');
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer',
        ];
    }
}
