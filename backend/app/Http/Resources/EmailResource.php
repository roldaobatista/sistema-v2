<?php

namespace App\Http\Resources;

use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Email
 */
class EmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'email_account_id' => $this->email_account_id,
            'message_id' => $this->message_id,
            'thread_id' => $this->thread_id,
            'folder' => $this->folder,
            'subject' => $this->subject,
            'from_name' => $this->from_name,
            'from_address' => $this->from_address,
            'to_addresses' => $this->to_addresses,
            'cc_addresses' => $this->cc_addresses,
            'snippet' => $this->snippet_text,
            'body_text' => $this->body_text,
            'body_html' => $this->body_html,
            'is_read' => $this->is_read,
            'is_starred' => $this->is_starred,
            'is_archived' => $this->is_archived,
            'has_attachments' => $this->has_attachments,
            'direction' => $this->direction,
            'status' => $this->status,
            'date' => $this->date?->toIso8601String(),
            'customer_id' => $this->customer_id,
            'linked_type' => $this->linked_type,
            'linked_id' => $this->linked_id,
            // AI classification fields
            'ai_category' => $this->ai_category,
            'ai_summary' => $this->ai_summary,
            'ai_sentiment' => $this->ai_sentiment,
            'ai_priority' => $this->ai_priority,
            'ai_suggested_action' => $this->ai_suggested_action,
            'ai_confidence' => $this->ai_confidence,
            'ai_classified_at' => $this->ai_classified_at?->toIso8601String(),
            // Assignment & scheduling
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'snoozed_until' => $this->snoozed_until?->toIso8601String(),
            // Send tracking
            'tracking_id' => $this->tracking_id,
            'read_count' => $this->read_count,
            'last_read_at' => $this->last_read_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('account')) {
            $arr['account'] = $this->account;
        }
        if ($this->relationLoaded('customer')) {
            $arr['customer'] = $this->customer;
        }
        if ($this->relationLoaded('attachments')) {
            $arr['attachments'] = $this->attachments;
        }
        if ($this->relationLoaded('linked')) {
            $arr['linked'] = $this->linked;
        }
        if ($this->relationLoaded('notes')) {
            $arr['notes'] = $this->notes;
        }
        if ($this->relationLoaded('tags')) {
            $arr['tags'] = $this->tags;
        }
        if ($this->relationLoaded('assignedTo')) {
            $arr['assigned_to'] = $this->assignedTo;
        }
        if ($this->relationLoaded('activities')) {
            $arr['activities'] = $this->activities;
        }
        if ($this->relationLoaded('thread')) {
            $arr['thread'] = $this->thread;
        }

        return $arr;
    }
}
