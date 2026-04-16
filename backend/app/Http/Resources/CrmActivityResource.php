<?php

namespace App\Http\Resources;

use App\Models\CrmActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmActivity
 */
class CrmActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'type' => $this->type,
            'customer_id' => $this->customer_id,
            'deal_id' => $this->deal_id,
            'user_id' => $this->user_id,
            'contact_id' => $this->contact_id,
            'title' => $this->title,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'outcome' => $this->outcome,
            'channel' => $this->channel,
            'is_automated' => $this->is_automated,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('deal')) {
            $arr['deal'] = $this->deal;
        }
        if ($this->relationLoaded('user')) {
            $arr['user'] = $this->user;
        }
        if ($this->relationLoaded('contact')) {
            $arr['contact'] = $this->contact;
        }

        return $arr;
    }
}
