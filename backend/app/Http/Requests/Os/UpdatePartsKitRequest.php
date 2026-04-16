<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartsKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description') && $this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'items' => 'sometimes|required|array|min:1',
            'items.*.type' => 'required|in:product,service',
            'items.*.reference_id' => 'nullable|integer',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do kit é obrigatório.',
            'items.required' => 'Informe ao menos um item no kit.',
            'items.*.type.in' => 'Tipo do item deve ser produto ou serviço.',
            'items.*.description.required' => 'A descrição do item é obrigatória.',
            'items.*.quantity.required' => 'A quantidade do item é obrigatória.',
            'items.*.unit_price.required' => 'O preço unitário do item é obrigatório.',
        ];
    }
}
