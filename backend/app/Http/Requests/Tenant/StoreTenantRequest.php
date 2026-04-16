<?php

namespace App\Http\Requests\Tenant;

use App\Enums\TenantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.tenant.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'document', 'email', 'phone', 'trade_name',
            'address_street', 'address_number', 'address_complement',
            'address_neighborhood', 'address_city', 'address_state', 'address_zip',
            'website', 'state_registration', 'city_registration',
        ];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        // Strip document mask (dots, slashes, dashes)
        if ($this->has('document') && $this->input('document') !== null && $this->input('document') !== '') {
            $cleaned['document'] = preg_replace('/[^0-9]/', '', $this->input('document'));
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $statusValues = array_map(fn ($c) => $c->value, TenantStatus::cases());

        return [
            'name' => 'required|string|max:255',
            'document' => [
                'nullable', 'string', 'max:20',
                Rule::unique('tenants', 'document')->whereNotNull('document'),
                'regex:/^(\d{11}|\d{14}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}|\d{3}\.\d{3}\.\d{3}-\d{2})$/',
            ],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => ['sometimes', Rule::in($statusValues)],
            'trade_name' => 'nullable|string|max:255',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_complement' => 'nullable|string|max:100',
            'address_neighborhood' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'address_zip' => 'nullable|string|max:10',
            'website' => 'nullable|url|max:255',
            'state_registration' => 'nullable|string|max:30',
            'city_registration' => 'nullable|string|max:30',
            'inmetro_config' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da empresa é obrigatório.',
            'document.unique' => 'Este documento já está cadastrado.',
        ];
    }
}
