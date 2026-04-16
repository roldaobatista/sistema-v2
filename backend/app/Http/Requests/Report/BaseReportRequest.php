<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BaseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view');
    }

    protected function prepareForValidation(): void
    {
        foreach (['from', 'to'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('branch_id') && $this->input('branch_id') === '') {
            $this->merge(['branch_id' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'os_number' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
        ];
    }
}
