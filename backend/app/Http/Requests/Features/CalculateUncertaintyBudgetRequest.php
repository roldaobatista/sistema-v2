<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class CalculateUncertaintyBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.view');
    }

    public function rules(): array
    {
        return [
            'readings' => 'required|array|min:2',
            'readings.*' => 'required|numeric',
            'resolution' => 'nullable|numeric|min:0',
            'coverage_factor' => 'nullable|numeric|min:0',
            'weight_uncertainty' => 'nullable|numeric|min:0',
            'weight_coverage_factor' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'readings.required' => 'As leituras são obrigatórias.',
            'readings.min' => 'Mínimo de 2 leituras necessárias para calcular incerteza.',
            'readings.*.numeric' => 'Cada leitura deve ser um valor numérico.',
        ];
    }
}
