<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class ReopenQuoteAction
{
    public function execute(Quote $quote): Quote
    {
        $allowedStatuses = [QuoteStatus::REJECTED, QuoteStatus::EXPIRED];
        if (! in_array($quote->status, $allowedStatuses, true)) {
            throw new \DomainException('Só é possível reabrir orçamentos rejeitados ou expirados');
        }

        return DB::transaction(function () use ($quote) {
            $updateData = [
                'status' => QuoteStatus::DRAFT->value,
                'rejected_at' => null,
                'rejection_reason' => null,
                'internal_approved_by' => null,
                'internal_approved_at' => null,
                'level2_approved_by' => null,
                'level2_approved_at' => null,
                'sent_at' => null,
                'approved_at' => null,
                'client_ip_approval' => null,
                'term_accepted_at' => null,
                'approval_channel' => null,
                'approval_notes' => null,
                'approved_by_name' => null,
            ];

            // Limpar valid_until se já expirou para evitar re-expiração imediata
            if ($quote->valid_until && $quote->valid_until->isPast()) {
                $updateData['valid_until'] = null;
            }

            $quote->update($updateData);
            $quote->increment('revision');
            AuditLog::log('status_changed', "Orçamento {$quote->quote_number} reaberto (rev. {$quote->revision})", $quote);

            return $quote;
        });
    }
}
