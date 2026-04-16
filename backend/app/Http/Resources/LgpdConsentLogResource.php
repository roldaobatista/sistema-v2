<?php

namespace App\Http\Resources;

use App\Models\LgpdConsentLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LgpdConsentLog
 */
class LgpdConsentLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'holder_type' => $this->holder_type,
            'holder_id' => $this->holder_id,
            'holder_name' => $this->holder_name,
            'holder_email' => $this->holder_email,
            'holder_document' => $this->holder_document,
            'purpose' => $this->purpose,
            'legal_basis' => $this->legal_basis,
            'status' => $this->status,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'revocation_reason' => $this->revocation_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
