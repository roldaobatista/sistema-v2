<?php

namespace App\Http\Requests\Quality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.document.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['description', 'version_number', 'effective_date', 'review_date', 'approved_by'];
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
        $tenantId = $this->tenantId();

        return [
            'title' => 'required|string|max:255',
            'document_type' => 'required|in:procedure,instruction,form,manual,policy,record',
            'description' => 'nullable|string',
            'file' => 'nullable|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,txt,ppt,pptx,jpg,jpeg,png',
            'version_number' => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'approved_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
