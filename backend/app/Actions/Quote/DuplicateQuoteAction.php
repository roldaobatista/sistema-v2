<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use Illuminate\Support\Facades\DB;

class DuplicateQuoteAction
{
    public function execute(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $newQuote = $quote->replicate([
                'quote_number',
                'revision',
                'status',
                'internal_approved_by',
                'internal_approved_at',
                'level2_approved_by',
                'level2_approved_at',
                'sent_at',
                'approved_at',
                'rejected_at',
                'rejection_reason',
                'last_followup_at',
                'followup_count',
                'client_viewed_at',
                'client_view_count',
                'magic_token',
                'client_ip_approval',
                'term_accepted_at',
                'is_installation_testing',
                'approval_channel',
                'approval_notes',
                'approved_by_name',
                'general_conditions',
            ]);
            $newQuote->quote_number = Quote::nextNumber($quote->tenant_id);
            $newQuote->revision = 1;
            $newQuote->status = QuoteStatus::DRAFT;
            $newQuote->internal_approved_by = null;
            $newQuote->internal_approved_at = null;
            $newQuote->level2_approved_by = null;
            $newQuote->level2_approved_at = null;
            $newQuote->sent_at = null;
            $newQuote->approved_at = null;
            $newQuote->rejected_at = null;
            $newQuote->rejection_reason = null;
            $newQuote->last_followup_at = null;
            $newQuote->followup_count = 0;
            $newQuote->client_viewed_at = null;
            $newQuote->client_view_count = 0;
            $newQuote->magic_token = null;
            $newQuote->client_ip_approval = null;
            $newQuote->term_accepted_at = null;
            $newQuote->is_installation_testing = false;
            $newQuote->approval_channel = null;
            $newQuote->approval_notes = null;
            $newQuote->approved_by_name = null;
            $newQuote->save();

            foreach ($quote->equipments as $eq) {
                /** @var QuoteEquipment $newEq */
                $newEq = $newQuote->equipments()->create([
                    'tenant_id' => $quote->tenant_id,
                    ...$eq->only(['equipment_id', 'description', 'sort_order']),
                ]);
                foreach ($eq->items as $item) {
                    $newEq->items()->create([
                        'tenant_id' => $quote->tenant_id,
                        ...$item->only([
                            'type', 'product_id', 'service_id', 'custom_description',
                            'quantity', 'original_price', 'cost_price', 'unit_price',
                            'discount_percentage', 'sort_order', 'internal_note',
                        ]),
                    ]);
                }
            }

            $newQuote->recalculateTotal();
            AuditLog::log('created', "Orçamento {$newQuote->quote_number} duplicado de {$quote->quote_number}", $newQuote);

            return $newQuote;
        });
    }
}
