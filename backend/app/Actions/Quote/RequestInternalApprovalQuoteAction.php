<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;

class RequestInternalApprovalQuoteAction
{
    public function execute(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::DRAFT) {
            throw new \DomainException('Apenas orçamentos em rascunho podem solicitar aprovação interna');
        }

        $hasItems = $quote->equipments()->whereHas('items')->exists();
        if (! $hasItems) {
            throw new \DomainException('Orçamento precisa ter pelo menos um equipamento com itens');
        }

        $quote->update(['status' => QuoteStatus::PENDING_INTERNAL_APPROVAL->value]);
        AuditLog::log('status_changed', "Orçamento {$quote->quote_number} enviado para aprovação interna", $quote);

        return $quote;
    }
}
