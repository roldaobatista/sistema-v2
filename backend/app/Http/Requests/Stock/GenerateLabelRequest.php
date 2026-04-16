<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.label.print');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'product_ids' => 'required_without:items|array|min:1',
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'items' => 'required_without:product_ids|array|min:1',
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'format_key' => 'required|string|max:50',
            'quantity' => 'sometimes|integer|min:1|max:100',
            'show_logo' => 'sometimes|boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
