<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmSequenceStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmSequenceRequest extends FormRequest
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
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer',
            'steps.*.delay_days' => 'required|integer|min:0',
            'steps.*.channel' => 'required|string',
            'steps.*.action_type' => ['required', Rule::in(CrmSequenceStep::ACTION_TYPES)],
            'steps.*.template_id' => 'nullable|integer',
            'steps.*.subject' => 'nullable|string',
            'steps.*.body' => 'nullable|string',
        ];
    }
}
