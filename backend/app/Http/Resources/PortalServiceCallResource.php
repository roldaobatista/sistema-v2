<?php

namespace App\Http\Resources;

use App\Models\ServiceCall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCall
 */
class PortalServiceCallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'call_number' => $this->call_number,
            'status' => $status,
            'status_label' => $this->status instanceof \BackedEnum && method_exists($this->status, 'label')
                ? $this->status->label()
                : null,
            'priority' => $this->priority,
            'observations' => $this->observations,
            'scheduled_date' => $this->scheduled_date?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'equipments' => $this->whenLoaded('equipments', fn () => $this->equipments->map(fn ($equipment): array => [
                'id' => $equipment->id,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'next_calibration_at' => $equipment->next_calibration_at?->toDateString(),
            ])->values()),
        ];
    }
}
