<?php

namespace App\Http\Requests\Equipment;

use App\Enums\EquipmentStatus;
use App\Http\Requests\Equipment\Concerns\ValidatesEquipmentIdentifiers;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    use ValidatesEquipmentIdentifiers;

    public function authorize(): bool
    {
        return $this->user()->can('equipments.equipment.update');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeEquipmentIdentifiers();

        if ($this->has('status')) {
            $normalizedStatus = EquipmentStatus::normalize($this->input('status'));
            if ($normalizedStatus !== null) {
                $this->merge(['status' => $normalizedStatus]);
            }
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->current_tenant_id ?? $this->user()->tenant_id;
        $equipmentId = $this->route('equipment')?->id ?? $this->route('equipment');

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:40',
            'brand' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                $this->duplicateEquipmentIdentifierRule('serial_number', 'numero de serie', $tenantId, $equipmentId ? (int) $equipmentId : null),
            ],
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'nullable|string|max:10',
            'resolution' => 'nullable|numeric|min:0',
            'precision_class' => 'nullable|in:I,II,III,IIII',
            'status' => ['nullable', Rule::in(array_keys(Equipment::STATUSES))],
            'location' => 'nullable|string|max:150',
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'purchase_date' => 'nullable|date',
            'purchase_value' => 'nullable|numeric|min:0',
            'warranty_expires_at' => 'nullable|date',
            'last_calibration_at' => 'nullable|date',
            'next_calibration_at' => 'nullable|date|after_or_equal:last_calibration_at',
            'calibration_interval_months' => 'nullable|integer|min:1',
            'inmetro_number' => [
                'nullable',
                'string',
                'max:50',
                $this->duplicateEquipmentIdentifierRule('inmetro_number', 'numero do INMETRO', $tenantId, $equipmentId ? (int) $equipmentId : null),
            ],
            'tag' => [
                'nullable',
                'string',
                'max:50',
                $this->duplicateEquipmentIdentifierRule('tag', 'tag', $tenantId, $equipmentId ? (int) $equipmentId : null),
            ],
            'is_critical' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'equipment_model_id' => ['nullable', Rule::exists('equipment_models', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => sprintf(
                'O status informado e invalido. Use um dos status permitidos: %s.',
                implode(', ', Equipment::STATUSES)
            ),
        ];
    }
}
