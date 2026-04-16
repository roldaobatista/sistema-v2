<?php

namespace App\Http\Resources;

use App\Models\LgpdDataRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LgpdDataRequest
 */
class LgpdDataRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'protocol' => $this->protocol,
            'holder_name' => $this->holder_name,
            'holder_email' => $this->holder_email,
            'holder_document' => $this->holder_document,
            'request_type' => $this->request_type,
            'status' => $this->status,
            'description' => $this->description,
            'response_notes' => $this->response_notes,
            'response_file_path' => $this->response_file_path,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'responded_by' => $this->responded_by,
            'created_by' => $this->created_by,
            'responder' => $this->whenLoaded('responder'),
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
