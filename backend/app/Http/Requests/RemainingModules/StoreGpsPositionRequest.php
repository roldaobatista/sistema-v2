<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGpsPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.tech_sync.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId)],
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'speed_kmh' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|min:0|max:360',
        ];
    }
}
