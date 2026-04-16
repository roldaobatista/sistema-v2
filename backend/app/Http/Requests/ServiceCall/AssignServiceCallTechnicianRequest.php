<?php

namespace App\Http\Requests\ServiceCall;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignServiceCallTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.assign');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['driver_id', 'scheduled_date'];
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
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;
        $validTenantUser = function (string $attribute, mixed $value, \Closure $fail) use ($tenantId) {
            if ($value === null) {
                return;
            }

            $exists = User::query()
                ->where('id', $value)
                ->where('is_active', true)
                ->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId))
                )
                ->exists();

            if (! $exists) {
                $fail('O '.($attribute === 'technician_id' ? 'técnico' : 'motorista').' selecionado é inválido.');
            }
        };

        return [
            'technician_id' => ['required', 'integer', $validTenantUser],
            'driver_id' => ['nullable', 'integer', $validTenantUser],
            'scheduled_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required' => 'O técnico é obrigatório.',
        ];
    }
}
