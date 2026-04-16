<?php

namespace App\Http\Requests\Crm;

use App\Models\ContactPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'target_type' => ['required', Rule::in(array_keys(ContactPolicy::TARGET_TYPES))],
            'target_value' => 'nullable|string|max:100',
            'max_days_without_contact' => 'required|integer|min:1|max:365',
            'warning_days_before' => 'integer|min:1|max:30',
            'preferred_contact_type' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ];
    }
}
