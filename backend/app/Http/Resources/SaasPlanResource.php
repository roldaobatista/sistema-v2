<?php

namespace App\Http\Resources;

use App\Models\SaasPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaasPlan
 */
class SaasPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'modules' => $this->modules,
            'max_users' => $this->max_users,
            'max_work_orders_month' => $this->max_work_orders_month,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subscriptions' => SaasSubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
