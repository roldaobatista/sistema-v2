<?php

namespace App\Actions\Quote;

use App\Enums\QuoteStatus;
use App\Jobs\SendQuoteEmailJob;
use App\Models\Quote;
use App\Models\QuoteEmail;

class SendQuoteEmailAction
{
    public function execute(Quote $quote, string $recipientEmail, ?string $recipientName, ?string $message, int $sentBy): QuoteEmail
    {
        $this->ensureQuoteReadyForCustomerSharing($quote);

        $emailLog = QuoteEmail::create([
            'tenant_id' => $quote->tenant_id,
            'quote_id' => $quote->id,
            'sent_by' => $sentBy,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => "Orçamento #{$quote->quote_number}",
            'status' => 'queued',
            'message_body' => $message,
            'pdf_attached' => true,
            'queued_at' => now(),
        ]);

        SendQuoteEmailJob::dispatch(
            $quote->id,
            $recipientEmail,
            $recipientName,
            $message,
            $sentBy,
            $emailLog->id,
        );

        return $emailLog;
    }

    private function ensureQuoteReadyForCustomerSharing(Quote $quote): void
    {
        $status = $quote->status;

        if ($status !== QuoteStatus::SENT || blank($quote->approval_url) || blank($quote->pdf_url)) {
            throw new \DomainException('Orçamento precisa ser enviado ao cliente antes de compartilhar link, WhatsApp ou e-mail.');
        }
    }
}
