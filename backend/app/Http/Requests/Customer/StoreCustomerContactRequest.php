<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.update');
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = ['role', 'phone', 'email'];
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
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do contato e obrigatorio.',
            'email.email' => 'O e-mail informado e invalido.',
        ];
    }
}
