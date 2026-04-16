<?php

namespace App\Http\Resources;

use App\Models\SaasSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasSubscription
 */
class SaasSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'billing_cycle' => $this->billing_cycle,
            'price' => $this->price,
            'discount' => $this->discount,
            'effective_price' => $this->effective_price,
            'started_at' => $this->started_at?->format('Y-m-d'),
            'trial_ends_at' => $this->trial_ends_at?->format('Y-m-d'),
            'current_period_start' => $this->current_period_start?->format('Y-m-d'),
            'current_period_end' => $this->current_period_end?->format('Y-m-d'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d'),
            'cancellation_reason' => $this->cancellation_reason,
            'payment_gateway' => $this->payment_gateway,
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'created_by' => $this->created_by,
            'plan' => new SaasPlanResource($this->whenLoaded('plan')),
            'creator' => $this->whenLoaded('creator'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
