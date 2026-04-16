<?php

namespace App\Http\Requests\SystemImprovements;

use App\Models\CapaRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCapaRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.create');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'type' => ['required', Rule::in(array_keys(CapaRecord::TYPES))],
            'source' => ['required', Rule::in(array_keys(CapaRecord::SOURCES))],
            'source_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'due_date' => 'nullable|date',
        ];
    }
}
