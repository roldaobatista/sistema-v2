<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class RoiCalculatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('innovation.view');
    }

    public function rules(): array
    {
        return [
            'monthly_os_count' => 'required|integer|min:1',
            'avg_os_value' => 'required|numeric|min:1',
            'current_monthly_cost' => 'required|numeric|min:0',
            'system_monthly_cost' => 'required|numeric|min:0',
            'time_saved_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
