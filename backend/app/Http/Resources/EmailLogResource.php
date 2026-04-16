<?php

namespace App\Http\Resources;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmailLog
 */
class EmailLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'to' => $this->to,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'error' => $this->error,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
