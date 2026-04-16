<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CreatePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.pipeline.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('product_category') && $this->input('product_category') === '') {
            $this->merge(['product_category' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'product_category' => 'nullable|string',
            'stages' => 'required|array|min:2',
            'stages.*.name' => 'required|string',
            'stages.*.probability' => 'required|integer|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do pipeline é obrigatório.',
            'stages.required' => 'Informe ao menos duas etapas.',
        ];
    }
}
