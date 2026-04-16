<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;

class RevertFromRenegotiationAction
{
    public function execute(Quote $quote, string $targetStatus, int $userId): Quote
    {
        $rawStatus = $quote->status->value;

        if ($rawStatus !== QuoteStatus::RENEGOTIATION->value) {
            throw new \DomainException('Apenas orçamentos em "Renegociação" podem ser revertidos.');
        }

        $allowed = [QuoteStatus::DRAFT->value, QuoteStatus::INTERNALLY_APPROVED->value];
        if (! in_array($targetStatus, $allowed, true)) {
            throw new \DomainException('Status de destino inválido para reversão.');
        }

        $quote->update(['status' => $targetStatus]);

        $label = $targetStatus === QuoteStatus::DRAFT->value ? 'rascunho' : 'aprovado internamente';
        AuditLog::log('updated', "Orçamento {$quote->quote_number} revertido de renegociação para {$label}", $quote);

        return $quote->fresh();
    }
}
