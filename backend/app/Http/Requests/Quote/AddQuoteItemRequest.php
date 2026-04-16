<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddQuoteItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['custom_description', 'discount_percentage'];
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
        $tenantId = $this->tenantId();

        return [
            'type' => 'required|in:product,service',
            'product_id' => ['nullable', 'required_if:type,product', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'service_id' => ['nullable', 'required_if:type,service', Rule::exists('services', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'custom_description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0.01',
            'original_price' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'O tipo do item é obrigatório.',
            'type.in' => 'Tipo deve ser produto ou serviço.',
            'product_id.required_if' => 'O produto é obrigatório quando o tipo é produto.',
            'service_id.required_if' => 'O serviço é obrigatório quando o tipo é serviço.',
            'quantity.required' => 'A quantidade é obrigatória.',
            'quantity.min' => 'A quantidade deve ser maior que zero.',
            'original_price.required' => 'O preço original é obrigatório.',
            'unit_price.required' => 'O preço unitário é obrigatório.',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
