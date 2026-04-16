<?php

namespace App\Http\Requests\ServiceCall;

use App\Models\ServiceCall;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCallRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        $legacyPriorityMap = [
            'medium' => 'normal',
        ];

        $normalized = [
            'observations' => $this->input('observations')
                ?? $this->input('description')
                ?? $this->input('subject')
                ?? $this->input('title'),
        ];

        if ($this->has('priority')) {
            $priority = $this->input('priority');
            $normalized['priority'] = $legacyPriorityMap[$priority] ?? $priority;
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return $this->user()->can('service_calls.service_call.update');
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;

        $validTenantUser = function (string $attribute, mixed $value, \Closure $fail) use ($tenantId) {
            if ($value === null) {
                return;
            }
            $exists = User::where('id', $value)
                ->where('is_active', true)
                ->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($sub) => $sub->where('tenants.id', $tenantId))
                )
                ->exists();
            if (! $exists) {
                $fail('O '.($attribute === 'technician_id' ? 'técnico' : 'motorista').' selecionado é inválido.');
            }
        };

        return [
            'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'technician_id' => ['nullable', 'integer', $validTenantUser],
            'driver_id' => ['nullable', 'integer', $validTenantUser],
            'priority' => ['nullable', Rule::in(array_keys(ServiceCall::PRIORITIES))],
            'scheduled_date' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_maps_link' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:5000',
            'subject' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'observations' => 'nullable|string|max:5000',
            'resolution_notes' => 'nullable|string|max:2000',
            'contract_id' => ['nullable', Rule::exists('contracts', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'template_id' => ['nullable', Rule::exists('service_call_templates', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipment_ids' => 'nullable|array',
            'equipment_ids.*' => [Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }
}
