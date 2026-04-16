<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class PeriodComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.analytics.view');
    }

    public function rules(): array
    {
        return [
            'period1_from' => 'required|date',
            'period1_to' => 'required|date|after_or_equal:period1_from',
            'period2_from' => 'required|date',
            'period2_to' => 'required|date|after_or_equal:period2_from',
        ];
    }
}
