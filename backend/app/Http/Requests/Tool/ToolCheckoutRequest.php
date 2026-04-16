<?php

namespace App\Http\Requests\Tool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ToolCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('condition_out') && $this->input('condition_out') === '') {
            $this->merge(['condition_out' => null]);
        }
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'tool_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'condition_out' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
