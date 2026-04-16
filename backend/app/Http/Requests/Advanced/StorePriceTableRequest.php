<?php

namespace App\Http\Requests\Advanced;

use App\Models\Lookups\PriceTableAdjustmentType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('commercial.price_table.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $fallback = ['markup' => 'Markup', 'discount' => 'Desconto'];
        $allowed = LookupValueResolver::allowedValues(PriceTableAdjustmentType::class, $fallback, $tenantId);

        return [
            'name' => 'required|string|max:255',
            'region' => 'nullable|string|max:100',
            'customer_type' => 'nullable|in:government,industry,commerce,agro',
            'multiplier' => 'nullable|numeric|min:0.0001|max:99.9999',
            'type' => ['nullable', 'string', Rule::in($allowed)],
            'modifier_percent' => 'nullable|numeric|min:0|max:999.99',
            'is_default' => 'nullable|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
        ];
    }
}
