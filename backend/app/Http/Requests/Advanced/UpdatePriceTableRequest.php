<?php

namespace App\Http\Requests\Advanced;

use App\Models\Lookups\PriceTableAdjustmentType;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceTableRequest extends FormRequest
{
    private const ADJUSTMENT_FALLBACK = ['markup' => 'Markup', 'discount' => 'Desconto'];

    public function authorize(): bool
    {
        return $this->user()->can('commercial.price_table.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $allowed = LookupValueResolver::allowedValues(PriceTableAdjustmentType::class, self::ADJUSTMENT_FALLBACK, $tenantId);

        return [
            'name' => 'sometimes|string|max:255',
            'region' => 'nullable|string|max:100',
            'customer_type' => 'nullable|in:government,industry,commerce,agro',
            'multiplier' => 'nullable|numeric|min:0.0001|max:99.9999',
            'type' => ['nullable', 'string', Rule::in($allowed)],
            'modifier_percent' => 'nullable|numeric|min:0|max:999.99',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
        ];
    }
}
