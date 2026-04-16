<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagementReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['participants', 'agenda', 'decisions', 'summary', 'actions'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = $field === 'actions' ? [] : null;
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
            'meeting_date' => 'required|date',
            'title' => 'required|string|max:255',
            'participants' => 'nullable|string|max:2000',
            'agenda' => 'nullable|string|max:5000',
            'decisions' => 'nullable|string|max:5000',
            'summary' => 'nullable|string|max:5000',
            'actions' => 'nullable|array',
            'actions.*.description' => 'required_with:actions|string|max:2000',
            'actions.*.responsible_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'actions.*.due_date' => 'nullable|date',
        ];
    }
}
