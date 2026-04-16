<?php

namespace App\Http\Resources;

use App\Models\CrmDeal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmDeal
 */
class CrmDealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'customer_id' => $this->customer_id,
            'pipeline_id' => $this->pipeline_id,
            'stage_id' => $this->stage_id,
            'title' => $this->title,
            'value' => $this->value,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date?->format('Y-m-d'),
            'source' => $this->source,
            'assigned_to' => $this->assigned_to,
            'quote_id' => $this->quote_id,
            'work_order_id' => $this->work_order_id,
            'equipment_id' => $this->equipment_id,
            'status' => $this->status,
            'won_at' => $this->won_at?->toIso8601String(),
            'lost_at' => $this->lost_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'loss_reason_id' => $this->loss_reason_id,
            'competitor_name' => $this->competitor_name,
            'competitor_price' => $this->competitor_price,
            'score' => $this->score,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('stage')) {
            $arr['stage'] = $this->stage;
        }
        if ($this->relationLoaded('pipeline')) {
            $arr['pipeline'] = $this->pipeline;
        }
        if ($this->relationLoaded('assignee')) {
            $arr['assignee'] = $this->assignee;
        }
        if ($this->relationLoaded('quote')) {
            $arr['quote'] = $this->quote;
        }
        if ($this->relationLoaded('workOrder')) {
            $arr['work_order'] = $this->workOrder;
        }
        if ($this->relationLoaded('equipment')) {
            $arr['equipment'] = $this->equipment;
        }
        if ($this->relationLoaded('activities')) {
            $arr['activities'] = CrmActivityResource::collection($this->activities);
        }
        if ($this->relationLoaded('lossReason')) {
            $arr['loss_reason_detail'] = $this->lossReason;
        }

        return $arr;
    }
}
