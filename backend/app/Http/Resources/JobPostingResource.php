<?php

namespace App\Http\Resources;

use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPosting
 */
class JobPostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'title' => $this->title,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'salary_range_min' => $this->salary_range_min,
            'salary_range_max' => $this->salary_range_max,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('department')) {
            $arr['department'] = $this->department;
        }
        if ($this->relationLoaded('position')) {
            $arr['position'] = $this->position;
        }
        if ($this->relationLoaded('candidates')) {
            $arr['candidates'] = $this->candidates;
        }

        return $arr;
    }
}
