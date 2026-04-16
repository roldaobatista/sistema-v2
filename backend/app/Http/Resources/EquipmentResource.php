<?php

namespace App\Http\Resources;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Equipment $equipment */
        $equipment = $this->resource;
        $status = $equipment->status;

        $arr = [
            'id' => $equipment->id,
            'tenant_id' => $equipment->tenant_id,
            'customer_id' => $equipment->customer_id,
            'code' => $equipment->code,
            'type' => $equipment->type,
            'category' => $equipment->category,
            'brand' => $equipment->brand,
            'manufacturer' => $equipment->manufacturer,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'capacity' => $equipment->capacity,
            'capacity_unit' => $equipment->capacity_unit,
            'equipment_model_id' => $equipment->equipment_model_id,
            'resolution' => $equipment->resolution,
            'precision_class' => $equipment->precision_class,
            'status' => EquipmentStatus::normalize($status) ?? $status,
            'location' => $equipment->location,
            'responsible_user_id' => $equipment->responsible_user_id,
            'purchase_date' => $this->formatDate($equipment->purchase_date),
            'purchase_value' => $equipment->purchase_value,
            'warranty_expires_at' => $this->formatDate($equipment->warranty_expires_at),
            'last_calibration_at' => $this->formatDate($equipment->last_calibration_at),
            'next_calibration_at' => $this->formatDate($equipment->next_calibration_at),
            'calibration_interval_months' => $equipment->calibration_interval_months,
            'inmetro_number' => $equipment->inmetro_number,
            'certificate_number' => $equipment->certificate_number,
            'tag' => $equipment->tag,
            'is_critical' => $equipment->is_critical,
            'is_active' => $equipment->is_active,
            'notes' => $equipment->notes,
            'created_at' => $equipment->created_at?->toIso8601String(),
            'updated_at' => $equipment->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('responsible')) {
            $arr['responsible'] = $this->responsible;
        }
        if ($this->relationLoaded('equipmentModel')) {
            $arr['equipment_model'] = $this->equipmentModel;
        }
        if ($this->relationLoaded('calibrations')) {
            $arr['calibrations'] = $this->calibrations;
        }
        if ($this->relationLoaded('maintenances')) {
            $arr['maintenances'] = $this->maintenances;
        }
        if ($this->relationLoaded('documents')) {
            $arr['documents'] = $this->documents;
        }
        if ($this->relationLoaded('workOrders')) {
            $arr['work_orders'] = $this->workOrders;
        }

        // calibration_status is always present (it's in $appends on the model)
        $arr['calibration_status'] = $this->calibration_status;

        // tracking_url is also appended by the model
        $arr['tracking_url'] = $this->tracking_url;

        return $arr;
    }

    private function formatDate(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->format('Y-m-d') : null;
    }
}
