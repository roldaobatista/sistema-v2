<?php

namespace App\Http\Resources;

use App\Models\EmailActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmailActivity
 */
class EmailActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'email_id' => $this->email_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'details' => $this->details,
            'user' => $this->whenLoaded('user'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
