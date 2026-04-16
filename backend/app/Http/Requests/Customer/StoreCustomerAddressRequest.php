<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.update');
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'type',
            'number',
            'complement',
            'district',
            'zip',
            'country',
            'latitude',
            'longitude',
        ];

        $normalized = [];
        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string|max:50',
            'street' => 'required|string|max:255',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|size:2',
            'zip' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'is_main' => 'sometimes|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'street.required' => 'A rua e obrigatoria.',
            'city.required' => 'A cidade e obrigatoria.',
            'state.required' => 'O estado e obrigatorio.',
            'state.size' => 'O estado deve possuir 2 caracteres.',
        ];
    }
}
