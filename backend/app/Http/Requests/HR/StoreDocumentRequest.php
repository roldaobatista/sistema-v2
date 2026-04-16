<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.document.manage');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['expiry_date', 'issued_date', 'issuer', 'notes'];
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
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereIn('id', fn ($sub) => $sub->select('user_id')->from('user_tenants')->where('tenant_id', $tenantId)))],
            'category' => 'required|in:aso,nr,contract,license,certification,id_doc,other',
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'expiry_date' => 'nullable|date',
            'issued_date' => 'nullable|date',
            'issuer' => 'nullable|string|max:255',
            'is_mandatory' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'O colaborador é obrigatório.',
            'category.required' => 'A categoria é obrigatória.',
            'name.required' => 'O nome do documento é obrigatório.',
            'file.required' => 'O arquivo é obrigatório.',
        ];
    }
}
