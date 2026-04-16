<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTvDashboardConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('tv.dashboard.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'is_default' => 'boolean',
            'default_mode' => 'string|in:dashboard,cameras,split',
            'rotation_interval' => 'integer|min:10',
            'camera_grid' => 'string|in:1x1,2x2,3x3,4x4',
            'alert_sound' => 'boolean',
            'kiosk_pin' => 'nullable|string|min:4',
            'technician_offline_minutes' => 'integer|min:1',
            'unattended_call_minutes' => 'integer|min:1',
            'kpi_refresh_seconds' => 'integer|min:10',
            'alert_refresh_seconds' => 'integer|min:15',
            'cache_ttl_seconds' => 'integer|min:10',
            'widgets' => 'nullable|array',
        ];
    }
}
