<?php

namespace App\Http\Requests\Calibration;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinearityTestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('calibration.reading.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'array', 'min:1'],
            'points.*.reference_value' => ['required', 'numeric'],
            'points.*.unit' => ['sometimes', 'string', 'max:20'],
            'points.*.indication_increasing' => ['nullable', 'numeric'],
            'points.*.indication_decreasing' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'points.required' => 'Informe ao menos um ponto de linearidade.',
            'points.*.reference_value.required' => 'O valor de referência é obrigatório para cada ponto.',
        ];
    }
}
