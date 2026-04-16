<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.alert.manage');
    }

    public function rules(): array
    {
        return [
            'is_enabled' => 'nullable|boolean',
            'channels' => 'nullable|array',
            'days_before' => 'nullable|integer',
            'recipients' => 'nullable|array',
            'escalation_hours' => 'nullable|integer|min:0',
            'escalation_recipients' => 'nullable|array',
            'escalation_recipients.*' => 'integer',
            'blackout_start' => 'nullable|string|max:5',
            'blackout_end' => 'nullable|string|max:5',
            'threshold_amount' => 'nullable|numeric|min:0',
        ];
    }
}
