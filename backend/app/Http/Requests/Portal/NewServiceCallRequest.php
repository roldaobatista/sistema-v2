<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewServiceCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('portal.service_call.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('equipment_id') && $this->input('equipment_id') === '') {
            $this->merge(['equipment_id' => null]);
        }
        if ($this->has('priority') && $this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where('tenant_id', $tenantId)],
            'description' => 'required|string|min:10',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ];
    }
}
