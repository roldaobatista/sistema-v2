<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\Equipment;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\ServiceType;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkOrderRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cleaned = [];

        if ($this->has('assignee_id') && ! $this->has('assigned_to')) {
            $cleaned['assigned_to'] = $this->input('assignee_id');
        }

        foreach (['service_type', 'lead_source', 'address', 'city', 'state', 'zip_code', 'contact_phone', 'agreed_payment_method', 'agreed_payment_notes'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        if ($this->has('scheduled_date') && $this->input('scheduled_date') === '') {
            $cleaned['scheduled_date'] = null;
        }

        if ($this->has('delivery_forecast') && $this->input('delivery_forecast') === '') {
            $cleaned['delivery_forecast'] = null;
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        $workOrder = $this->route('workOrder') ?? $this->route('work_order');
        $isFinalStatus = $workOrder && in_array($workOrder->status, [
            WorkOrder::STATUS_INVOICED,
        ], true);

        $isClosedStatus = $workOrder && in_array($workOrder->status, [
            WorkOrder::STATUS_COMPLETED,
            WorkOrder::STATUS_DELIVERED,
            WorkOrder::STATUS_INVOICED,
        ], true);

        $rules = [
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'description' => 'sometimes|string',
            'internal_notes' => 'nullable|string',
            'technical_report' => 'nullable|string',
            'displacement_value' => 'sometimes|numeric|min:0',
            'is_warranty' => 'sometimes|boolean',
        ];

        if (! $isFinalStatus) {
            $rules += [
                'customer_id' => ['sometimes', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'scheduled_date' => 'nullable|date',
                'received_at' => 'nullable|date',
                'os_number' => 'nullable|string|max:30',
                'seller_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'driver_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'service_type' => ['nullable', 'string', Rule::in($this->allowedValues(ServiceType::class, WorkOrder::SERVICE_TYPES))],
                'lead_source' => ['nullable', 'string', Rule::in($this->allowedValues(LeadSource::class, WorkOrder::LEAD_SOURCES))],
                'delivery_forecast' => 'nullable|date',
                'parent_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)), function ($attribute, $value, $fail) {
                    $workOrder = $this->route('workOrder') ?? $this->route('work_order');
                    if ($value && $workOrder && $value == $workOrder->id) {
                        $fail('Uma OS não pode ser pai de si mesma.');

                        return;
                    }
                    // Prevent circular references and depth > 3
                    if ($value && $workOrder) {
                        $ancestor = WorkOrder::find($value);
                        $depth = 0;
                        while ($ancestor && $depth <= 3) {
                            if ($ancestor->id === $workOrder->id) {
                                $fail('Referência circular detectada (a nova OS pai é uma descendente desta OS).');

                                return;
                            }
                            if ($ancestor->parent_id) {
                                $ancestor = WorkOrder::find($ancestor->parent_id);
                                $depth++;
                            } else {
                                break;
                            }
                        }
                        if ($depth >= 3) {
                            $fail('Profundidade máxima de sub-OS atingida (máximo 3 níveis).');
                        }
                    }
                }],
                'branch_id' => ['nullable', Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50',
                'photo_checklist' => 'nullable|array',
                'technician_ids' => 'nullable|array',
                'technician_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'equipment_ids' => 'nullable|array',
                'equipment_ids.*' => ['integer', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:2',
                'zip_code' => 'nullable|string|max:10',
                'contact_phone' => 'nullable|string|max:20',
                'agreed_payment_method' => ['nullable', 'string', 'max:50'],
                'agreed_payment_notes' => 'nullable|string|max:500',
                'sla_policy_id' => ['nullable', Rule::exists('sla_policies', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                'checklist_id' => ['nullable', Rule::exists('service_checklists', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
                // Análise Crítica (ISO 17025 / Calibração)
                'service_modality' => 'nullable|string|max:50',
                'requires_adjustment' => 'sometimes|boolean',
                'requires_maintenance' => 'sometimes|boolean',
                'client_wants_conformity_declaration' => 'sometimes|boolean',
                'decision_rule_agreed' => 'nullable|in:simple,guard_band,shared_risk',
                'subject_to_legal_metrology' => 'sometimes|boolean',
                'needs_ipem_interaction' => 'sometimes|boolean',
                'site_conditions' => 'nullable|string|max:1000',
                'calibration_scope_notes' => 'nullable|string|max:1000',
                'applicable_procedure' => 'nullable|string|max:500',
                'will_emit_complementary_report' => 'sometimes|boolean',
                'client_accepted_at' => 'nullable|date',
                'client_accepted_by' => 'nullable|string|max:255',
            ];
        }

        if (! $isClosedStatus) {
            $rules += [
                'discount' => 'nullable|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'Cliente inválido para este tenant.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customerId = $this->customerIdFromInput();
            if (! $customerId) {
                return;
            }

            $tenantId = $this->tenantId();

            if ($equipmentId = $this->nullableInt('equipment_id')) {
                $equipmentMatchesCustomer = Equipment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($equipmentId)
                    ->where('customer_id', $customerId)
                    ->exists();

                if (! $equipmentMatchesCustomer) {
                    $validator->errors()->add('equipment_id', 'O equipamento principal nao pertence ao cliente selecionado.');
                }
            }

            $equipmentIds = collect($this->input('equipment_ids', []))
                ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
                ->map(fn (mixed $value): int => (int) $value)
                ->values();

            if ($equipmentIds->isEmpty()) {
                return;
            }

            $invalidEquipmentIds = Equipment::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $equipmentIds)
                ->where('customer_id', '!=', $customerId)
                ->pluck('id');

            if ($invalidEquipmentIds->isNotEmpty()) {
                $validator->errors()->add('equipment_ids', 'Um ou mais equipamentos selecionados nao pertencem ao cliente informado.');
            }
        });
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }

    private function customerIdFromInput(): ?int
    {
        $customerId = $this->input('customer_id');

        if ($customerId !== null && $customerId !== '') {
            return (int) $customerId;
        }

        $workOrder = $this->route('workOrder') ?? $this->route('work_order');

        return $workOrder?->customer_id ? (int) $workOrder->customer_id : null;
    }

    private function nullableInt(string $field): ?int
    {
        $value = $this->input($field);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $fallback
     * @return array<int, string>
     */
    private function allowedValues(string $modelClass, array $fallback): array
    {
        $fallbackValues = array_values(array_unique([
            ...array_keys($fallback),
            ...array_values($fallback),
        ]));

        $table = (new $modelClass)->getTable();
        if (! Schema::hasTable($table)) {
            return $fallbackValues;
        }

        $lookupValues = $modelClass::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->get(['slug', 'name'])
            ->flatMap(fn ($item) => [$item->slug, $item->name])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();

        return array_values(array_unique([
            ...$lookupValues,
            ...$fallbackValues,
        ]));
    }
}
