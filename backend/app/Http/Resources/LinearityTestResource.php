<?php

namespace App\Http\Resources;

use App\Models\LinearityTest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LinearityTest
 */
class LinearityTestResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'point_order' => $this->point_order,
            'reference_value' => $this->reference_value,
            'unit' => $this->unit,
            'indication_increasing' => $this->indication_increasing,
            'indication_decreasing' => $this->indication_decreasing,
            'error_increasing' => $this->error_increasing,
            'error_decreasing' => $this->error_decreasing,
            'hysteresis' => $this->hysteresis,
            'max_permissible_error' => $this->max_permissible_error,
            'conforms' => $this->conforms,
        ];
    }
}
