<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFunnelAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trigger_days') && $this->input('trigger_days') === '') {
            $this->merge(['trigger_days' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'trigger' => 'sometimes|string|in:on_enter,on_exit,after_days',
            'trigger_days' => 'nullable|integer|min:1',
            'subject' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'is_active' => 'boolean',
        ];
    }
}
