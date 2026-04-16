<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreTravelRequestRequest extends FormRequest
{
    use ValidatesTenantUser;

    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', $this->tenantUserExistsRule()],
            'destination' => 'required|string|max:255',
            'purpose' => 'required|string|max:1000',
            'departure_date' => 'required|date|after_or_equal:today',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'departure_time' => 'nullable|date_format:H:i',
            'return_time' => 'nullable|date_format:H:i',
            'estimated_days' => 'required|integer|min:1|max:90',
            'daily_allowance_amount' => 'nullable|numeric|min:0',
            'total_advance_requested' => 'nullable|numeric|min:0',
            'requires_vehicle' => 'required|boolean',
            'fleet_vehicle_id' => 'nullable|integer|exists:fleet_vehicles,id',
            'requires_overnight' => 'required|boolean',
            'rest_days_after' => 'nullable|integer|min:0|max:7',
            'overtime_authorized' => 'required|boolean',
            'work_orders' => 'nullable|array',
            'itinerary' => 'nullable|array',
            'meal_policy' => 'nullable|array',
        ];
    }
}
