<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.document.update');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'effective_date', 'review_date', 'approved_by'];
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
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,review,approved,obsolete',
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'approved_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }
}
