<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class StoreSequenceTraitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.sequence.manage');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_conditions' => 'nullable|array',
            'status' => 'in:draft,active,paused',
            'steps' => 'required|array|min:1',
            'steps.*.action_type' => 'required|string',
            'steps.*.config' => 'required|array',
            'steps.*.delay_days' => 'required|integer|min:0',
            'steps.*.sort_order' => 'required|integer',
        ];
    }
}
