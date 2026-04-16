<?php

namespace App\Http\Requests\ServiceCall;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookCreateServiceCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['priority', 'observations', 'address', 'city', 'state'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id;

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'observations' => 'nullable|string|max:3000',
            'address' => 'nullable|string|max:300',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'O cliente é obrigatório.',
        ];
    }
}
