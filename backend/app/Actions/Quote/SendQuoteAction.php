<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Models\AuditLog;
use App\Models\Quote;
use Illuminate\Support\Str;

class SendQuoteAction
{
    public function execute(Quote $quote): Quote
    {
        if ($quote->status !== QuoteStatus::INTERNALLY_APPROVED) {
            throw new \DomainException('Orçamento precisa estar aprovado internamente antes de enviar ao cliente');
        }

        $hasItems = $quote->equipments()->whereHas('items')->exists();
        if (! $hasItems) {
            throw new \DomainException('Orçamento precisa ter pelo menos um equipamento com itens para ser enviado');
        }

        $quote->update([
            'status' => QuoteStatus::SENT->value,
            'sent_at' => now(),
            'magic_token' => $quote->magic_token ?: Str::random(64),
        ]);

        AuditLog::log('status_changed', "Orçamento {$quote->quote_number} enviado ao cliente", $quote);

        return $quote;
    }
}
