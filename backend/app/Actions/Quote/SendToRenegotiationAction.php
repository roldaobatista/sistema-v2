<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;

class SendToRenegotiationAction
{
    public function execute(Quote $quote, int $userId): Quote
    {
        $rawStatus = $quote->status->value;

        if ($rawStatus !== QuoteStatus::INSTALLATION_TESTING->value) {
            throw new \DomainException('Apenas orçamentos em "Instalação p/ Teste" podem ir para renegociação.');
        }

        $quote->update([
            'status' => QuoteStatus::RENEGOTIATION->value,
        ]);

        AuditLog::log('updated', "Orçamento {$quote->quote_number} enviado para renegociação", $quote);

        return $quote->fresh();
    }
}
