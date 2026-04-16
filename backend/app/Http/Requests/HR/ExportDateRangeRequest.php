<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class ExportDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.export');
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }
}
