<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class SimulateRegulatoryImpactInmetroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.view');
    }

    public function rules(): array
    {
        return [
            'current_period_months' => 'required|integer|min:1',
            'new_period_months' => 'required|integer|min:1',
            'affected_types' => 'nullable|array',
        ];
    }
}
