<?php

namespace App\Http\Requests\WorkOrder;

use App\Models\Equipment;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\ServiceType;
use App\Models\Quote;
use App\Models\ServiceCall;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = [
            'equipment_id', 'assigned_to', 'seller_id', 'driver_id',
            'quote_id', 'service_call_id', 'service_type', 'os_number',
            'scheduled_date', 'received_at', 'completed_at', 'started_at', 'delivered_at',
            'manual_justification', 'internal_notes', 'lead_source', 'delivery_forecast',
            'address', 'city', 'state', 'zip_code', 'contact_phone',
        ];

        $cleaned = [];

        if ($this->has('assignee_id') && ! $this->has('assigned_to')) {
            $cleaned['assigned_to'] = $this->input('assignee_id');
        }

        if (! $this->filled('description') && $this->filled('title')) {
            $cleaned['description'] = $this->input('title');
        }

        if ($this->input('priority') === 'medium') {
            $cleaned['priority'] = WorkOrder::PRIORITY_NORMAL;
        }

        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        if (! $this->has('branch_id') || $this->branch_id === null) {
            $cleaned['branch_id'] = $this->user()->branch_id ?? null;
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }

        if ($this->filled('service_call_id')) {
            $this->merge(['origin_type' => WorkOrder::ORIGIN_SERVICE_CALL]);
        } elseif ($this->filled('quote_id')) {
            $this->merge(['origin_type' => WorkOrder::ORIGIN_QUOTE]);
        } elseif (! $this->filled('origin_type') || $this->input('origin_type') === 'direct') {
            $this->merge(['origin_type' => WorkOrder::ORIGIN_MANUAL]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipment_id' => ['nullable', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'description' => 'required|string',
            'internal_notes' => 'nullable|string',
            'scheduled_date' => 'nullable|date|after_or_equal:today',
            'received_at' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'os_number' => 'nullable|string|max:30',
            'quote_id' => ['nullable', Rule::exists('quotes', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'service_call_id' => ['nullable', Rule::exists('service_calls', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'seller_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'driver_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'origin_type' => ['nullable', Rule::in([
                WorkOrder::ORIGIN_QUOTE,
                WorkOrder::ORIGIN_SERVICE_CALL,
                WorkOrder::ORIGIN_RECURRING,
                WorkOrder::ORIGIN_MANUAL,
                'direct',
            ])],
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'displacement_value' => 'nullable|numeric|min:0',
            'is_warranty' => 'sometimes|boolean',
            'technician_ids' => 'nullable|array',
            'technician_ids.*' => [Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipment_ids' => 'nullable|array',
            'equipment_ids.*' => [Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'new_equipment' => 'nullable|array',
            'new_equipment.type' => 'required_with:new_equipment|string|max:100',
            'new_equipment.brand' => 'nullable|string|max:100',
            'new_equipment.model' => 'nullable|string|max:100',
            'new_equipment.serial_number' => 'nullable|string|max:255',
            'items' => 'array',
            'items.*.type' => 'required|in:product,service',
            'items.*.reference_id' => 'nullable|integer',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'sometimes|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|numeric|min:0',
            'items.*.discount' => 'sometimes|numeric|min:0',
            'items.*.cost_price' => 'sometimes|numeric|min:0',
            'service_type' => ['nullable', 'string', Rule::in($this->allowedValues(ServiceType::class, WorkOrder::SERVICE_TYPES))],
            'manual_justification' => 'nullable|string|max:1000',
            'lead_source' => ['nullable', 'string', Rule::in($this->allowedValues(LeadSource::class, WorkOrder::LEAD_SOURCES))],
            'delivery_forecast' => 'nullable|date',
            'parent_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)), function ($attribute, $value, $fail) {
                if ($value && $this->route('work_order') && $value == $this->route('work_order')->id) {
                    $fail('Uma OS não pode ser pai de si mesma.');
                }
                // Prevent depth > 3
                if ($value) {
                    $parent = WorkOrder::find($value);
                    $depth = 0;
                    while ($parent && $depth < 3) {
                        if ($parent->parent_id) {
                            $parent = WorkOrder::find($parent->parent_id);
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
            'initial_status' => 'sometimes|in:open,awaiting_dispatch,in_displacement,in_service,completed,delivered,invoiced',
            'completed_at' => 'nullable|date',
            'started_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
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

    public function messages(): array
    {
        return [
            'customer_id.required' => 'O cliente e obrigatório.',
            'customer_id.exists' => 'Cliente inválido.',
            'description.required' => 'A descricao e obrigatoria.',
            'items.*.description.required' => 'A descricao do item e obrigatoria.',
            'items.*.type.required' => 'O tipo do item e obrigatório.',
            'items.*.type.in' => 'O tipo do item deve ser produto ou servico.',
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

            if ($quoteId = $this->nullableInt('quote_id')) {
                $quoteMatchesCustomer = Quote::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($quoteId)
                    ->where('customer_id', $customerId)
                    ->exists();

                if (! $quoteMatchesCustomer) {
                    $validator->errors()->add('quote_id', 'O orçamento informado nao pertence ao cliente selecionado.');
                }
            }

            if ($serviceCallId = $this->nullableInt('service_call_id')) {
                $serviceCallMatchesCustomer = ServiceCall::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($serviceCallId)
                    ->where('customer_id', $customerId)
                    ->exists();

                if (! $serviceCallMatchesCustomer) {
                    $validator->errors()->add('service_call_id', 'O chamado informado nao pertence ao cliente selecionado.');
                }
            }

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

        if ($customerId === null || $customerId === '') {
            return null;
        }

        return (int) $customerId;
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
