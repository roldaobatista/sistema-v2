<?php

namespace App\Http\Resources;

use App\Models\NumberingSequence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NumberingSequence
 */
class NumberingSequenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'entity' => $this->entity,
            'prefix' => $this->prefix,
            'next_number' => $this->next_number,
            'padding' => $this->padding,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('branch')) {
            $arr['branch'] = $this->branch;
        }

        return $arr;
    }
}
