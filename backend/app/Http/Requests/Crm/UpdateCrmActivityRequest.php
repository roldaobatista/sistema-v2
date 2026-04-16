<?php

namespace App\Http\Requests\Crm;

use App\Models\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'scheduled_at', 'completed_at', 'duration_minutes', 'outcome', 'channel', 'metadata'];
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
        return [
            'type' => ['sometimes', Rule::in(array_keys(CrmActivity::TYPES))],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:0',
            'outcome' => ['nullable', Rule::in(array_keys(CrmActivity::OUTCOMES))],
            'channel' => ['nullable', Rule::in(array_keys(CrmActivity::CHANNELS))],
            'metadata' => 'nullable|array',
        ];
    }
}
