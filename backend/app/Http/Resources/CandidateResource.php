<?php

namespace App\Http\Resources;

use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
class CandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'job_posting_id' => $this->job_posting_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'resume_path' => $this->resume_path,
            'stage' => $this->stage,
            'notes' => $this->notes,
            'rating' => $this->rating,
            'rejected_reason' => $this->rejected_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
