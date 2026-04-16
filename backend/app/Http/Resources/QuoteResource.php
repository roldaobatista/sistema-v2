<?php

namespace App\Http\Resources;

use App\Enums\QuoteStatus;
use App\Models\ClientPortalUser;
use App\Models\Quote;
use App\Support\QuotePaymentSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
{
    private bool $publicContract = false;

    public static function forPublicContract(Quote $quote): self
    {
        $resource = new self($quote);
        $resource->publicContract = true;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status : QuoteStatus::tryFrom($this->status);
        $paymentSummary = QuotePaymentSummary::fromQuote($this->resource);

        if ($this->publicContract || $this->isExternalPortalRequest($request)) {
            return [
                'id' => $this->id,
                'quote_number' => $this->quote_number,
                'revision' => $this->revision,
                'status' => $status instanceof \BackedEnum ? $status->value : $this->status,
                'status_label' => $status instanceof QuoteStatus ? $status->label() : null,
                'status_color' => $status instanceof QuoteStatus ? $status->color() : null,
                'valid_until' => $this->valid_until?->format('Y-m-d'),
                'discount_percentage' => $this->discount_percentage,
                'discount_amount' => $this->discount_amount,
                'displacement_value' => $this->displacement_value,
                'subtotal' => $this->subtotal,
                'total' => $this->total,
                'currency' => $this->currency,
                'observations' => $this->observations,
                'payment_terms' => $this->payment_terms,
                'payment_terms_detail' => $this->payment_terms_detail,
                'payment_method_label' => $paymentSummary['method_label'],
                'payment_condition_summary' => $paymentSummary['condition_summary'],
                'payment_detail_text' => $paymentSummary['detail_text'],
                'payment_schedule' => $paymentSummary['schedule'],
                'general_conditions' => $this->general_conditions,
                'approved_at' => $this->approved_at?->toIso8601String(),
                'rejected_at' => $this->rejected_at?->toIso8601String(),
                'rejection_reason' => $this->rejection_reason,
                'pdf_url' => $this->pdf_url,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'customer' => $this->when($this->relationLoaded('customer'), fn () => [
                    'name' => $this->customer?->name,
                ]),
                'seller' => $this->when($this->relationLoaded('seller'), fn () => [
                    'name' => $this->seller?->name,
                ]),
                'equipments' => $this->when($this->relationLoaded('equipments'), fn () => $this->equipments->map(fn ($equipment) => [
                    'description' => $equipment->description ?? null,
                    'equipment' => $equipment->relationLoaded('equipment') && $equipment->equipment ? [
                        'brand' => $equipment->equipment->brand,
                        'model' => $equipment->equipment->model,
                        'serial_number' => $equipment->equipment->serial_number,
                    ] : null,
                    'items' => $equipment->relationLoaded('items') ? $equipment->items->map(fn ($item) => [
                        'description' => $item->description ?? 'Item do orçamento',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount_percentage' => $item->discount_percentage,
                        'subtotal' => $item->subtotal,
                    ])->values() : [],
                ])->values()),
            ];
        }

        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'quote_number' => $this->quote_number,
            'revision' => $this->revision,
            'customer_id' => $this->customer_id,
            'seller_id' => $this->seller_id,
            'status' => $status instanceof \BackedEnum ? $status->value : $this->status,
            'status_label' => $status instanceof QuoteStatus ? $status->label() : null,
            'status_color' => $status instanceof QuoteStatus ? $status->color() : null,
            'source' => $this->source,
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'discount_percentage' => $this->discount_percentage,
            'discount_amount' => $this->discount_amount,
            'displacement_value' => $this->displacement_value,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'currency' => $this->currency,
            'observations' => $this->observations,
            'internal_notes' => $this->internal_notes,
            'payment_terms' => $this->payment_terms,
            'payment_terms_detail' => $this->payment_terms_detail,
            'payment_method_label' => $paymentSummary['method_label'],
            'payment_condition_summary' => $paymentSummary['condition_summary'],
            'payment_detail_text' => $paymentSummary['detail_text'],
            'payment_schedule' => $paymentSummary['schedule'],
            'is_installation_testing' => $this->is_installation_testing,
            'is_template' => $this->is_template,
            'template_id' => $this->template_id,
            'general_conditions' => $this->general_conditions,
            'custom_fields' => $this->custom_fields,
            'magic_token' => $this->when(
                $request->user()?->can('quotes.quote.send'),
                $this->magic_token
            ),
            'approval_channel' => $this->approval_channel,
            'approval_notes' => $this->approval_notes,
            'approved_by_name' => $this->approved_by_name,
            'internal_approved_by' => $this->internal_approved_by,
            'internal_approved_at' => $this->internal_approved_at?->toIso8601String(),
            'level2_approved_by' => $this->level2_approved_by,
            'level2_approved_at' => $this->level2_approved_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'last_followup_at' => $this->last_followup_at?->toIso8601String(),
            'followup_count' => $this->followup_count,
            'client_view_count' => $this->client_view_count,
            'client_viewed_at' => $this->client_viewed_at?->toIso8601String(),
            'term_accepted_at' => $this->term_accepted_at?->toIso8601String(),
            'client_ip_approval' => $this->client_ip_approval,
            'opportunity_id' => $this->opportunity_id,
            'validity_days' => $this->validity_days,
            'created_by' => $this->created_by,
            'approval_token' => $this->approval_token,
            'approval_url' => $this->approval_url,
            'pdf_url' => $this->pdf_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('seller')) {
            $arr['seller'] = $this->seller;
        }
        if ($this->relationLoaded('equipments')) {
            $arr['equipments'] = $this->equipments;
        }
        if ($this->relationLoaded('tags')) {
            $arr['tags'] = $this->tags;
        }
        if ($this->relationLoaded('emails')) {
            $arr['emails'] = $this->emails;
        }
        if ($this->relationLoaded('workOrders')) {
            $arr['work_orders'] = $this->workOrders;
        }
        if ($this->relationLoaded('serviceCalls')) {
            $arr['service_calls'] = $this->serviceCalls;
        }
        if ($this->relationLoaded('internalApprover')) {
            $arr['internal_approver'] = $this->internalApprover;
        }
        if ($this->relationLoaded('level2Approver')) {
            $arr['level2_approver'] = $this->level2Approver;
        }
        if ($this->relationLoaded('creator')) {
            $arr['creator'] = $this->creator;
        }
        if ($this->relationLoaded('template')) {
            $arr['template'] = $this->template;
        }
        if ($this->relationLoaded('accountReceivables')) {
            $arr['account_receivables'] = $this->accountReceivables;
        }

        return $arr;
    }

    private function isExternalPortalRequest(Request $request): bool
    {
        return $request->user() instanceof ClientPortalUser
            || $request->is('api/v1/portal/*');
    }
}
