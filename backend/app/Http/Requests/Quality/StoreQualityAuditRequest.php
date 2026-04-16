<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQualityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.audit.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['scheduled_date', 'auditor_id', 'scope', 'summary'];
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
            'title' => 'required|string|max:255',
            'type' => 'required|in:internal,external,process,product,supplier',
            'planned_date' => 'required_without:scheduled_date|date',
            'scheduled_date' => 'nullable|date',
            'auditor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'scope' => 'nullable|string|max:2000',
            'summary' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
            'items.*.question' => 'required_without:items.*.description|string|max:1000',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.requirement' => 'nullable|string|max:500',
            'items.*.clause' => 'nullable|string|max:100',
            'items.*.result' => 'nullable|in:conform,non_conform,observation,not_applicable,conforming,non_conforming',
            'items.*.status' => 'nullable|in:conform,non_conform,observation,not_applicable,conforming,non_conforming',
            'items.*.evidence' => 'nullable|string|max:1000',
            'items.*.notes' => 'nullable|string|max:2000',
        ];
    }
}
