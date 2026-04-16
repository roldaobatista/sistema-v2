<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('estoque.label.print');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'format_key' => 'required|string|max:50',
            'show_logo' => 'sometimes|in:0,1,true,false',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
