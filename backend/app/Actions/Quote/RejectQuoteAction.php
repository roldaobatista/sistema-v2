<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RejectQuoteAction
{
    public function execute(Quote $quote, ?string $reason): Quote
    {
        if ($quote->status !== QuoteStatus::SENT) {
            throw new \DomainException('Orçamento precisa estar enviado para rejeitar');
        }

        return DB::transaction(function () use ($quote, $reason) {
            $quote->update([
                'status' => QuoteStatus::REJECTED->value,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
            AuditLog::log('status_changed', "Orçamento {$quote->quote_number} rejeitado", $quote);

            // Notificar vendedor da rejeição
            try {
                $seller = $quote->seller ?? ($quote->seller_id ? User::find($quote->seller_id) : null);
                if ($seller) {
                    $notification = new QuoteStatusNotification($quote->fresh(['customer']), 'rejected', $reason);
                    $notification->persistToDatabase($quote->tenant_id, $seller->id);
                    $seller->notify($notification);
                }
            } catch (\Throwable $e) {
                Log::warning('Quote rejection notification failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            }

            return $quote;
        });
    }
}
