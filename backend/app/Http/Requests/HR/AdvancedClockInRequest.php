<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvancedClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['geofence_location_id', 'work_order_id'];
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
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'altitude' => 'nullable|numeric',
            'speed' => 'nullable|numeric|min:0',
            'type' => 'nullable|in:regular,overtime,travel',
            'liveness_score' => 'nullable|numeric|between:0,1',
            'clock_method' => 'nullable|in:selfie,qrcode,manual',
            'geofence_location_id' => ['nullable', Rule::exists('geofence_locations', 'id')->where('tenant_id', $tenantId)],
            'selfie' => 'required',
            'device_info' => 'nullable|array',
            'work_order_id' => ['nullable', Rule::exists('work_orders', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Localização GPS é obrigatória para registro de ponto (Portaria 671).',
            'longitude.required' => 'Localização GPS é obrigatória para registro de ponto (Portaria 671).',
            'selfie.required' => 'Selfie é obrigatória para registro de ponto (Portaria 671).',
        ];
    }
}
