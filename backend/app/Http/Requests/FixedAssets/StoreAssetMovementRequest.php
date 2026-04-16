<?php

namespace App\Http\Requests\FixedAssets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fixed_assets.asset.update');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'movement_type' => ['required', Rule::in(['transfer', 'assignment', 'maintenance', 'inventory_adjustment'])],
            'to_location' => ['nullable', 'string', 'max:255'],
            'to_responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'moved_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
