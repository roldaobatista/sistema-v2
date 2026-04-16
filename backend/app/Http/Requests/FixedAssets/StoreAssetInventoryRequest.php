<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.inventory.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_date' => ['required', 'date'],
            'counted_location' => ['nullable', 'string', 'max:255'],
            'counted_status' => ['nullable', Rule::in(['active', 'suspended', 'disposed', 'fully_depreciated'])],
            'condition_ok' => ['sometimes', 'boolean'],
            'offline_reference' => ['nullable', 'string', 'max:100'],
            'synced_from_pwa' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
