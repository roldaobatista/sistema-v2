<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\QuoteEmail;
use App\Services\PdfGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendQuoteEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private int $quoteId,
        private string $recipientEmail,
        private ?string $recipientName,
        private ?string $message,
        private int $sentBy,
        private int $emailLogId,
    ) {
        $this->queue = 'emails';
    }

    public function handle(): void
    {
        // Job enfileirado sem binding current_tenant_id. Usar withoutGlobalScope('tenant')
        // explícito (não withoutGlobalScopes() agressivo que remove soft-delete também).
        // Logo após carregar, bindamos o tenant do próprio Quote (linha seguinte).
        $quote = Quote::withoutGlobalScope('tenant')->with([
            'customer',
            'seller',
            'equipments.equipment',
            'equipments.items.product',
            'equipments.items.service',
        ])->findOrFail($this->quoteId);
        app()->instance('current_tenant_id', $quote->tenant_id);

        try {
            $pdfService = app(PdfGeneratorService::class);
            $pdfContent = $pdfService->renderQuotePdf($quote)->output();

            $subject = "Orçamento #{$quote->quote_number}";

            Mail::send('emails.quote-ready', [
                'quote' => $quote,
                'customerName' => $this->recipientName ?? $quote->customer?->name ?? 'Cliente',
                'total' => number_format((float) $quote->total, 2, ',', '.'),
                'approvalUrl' => $quote->approval_url,
                'customMessage' => $this->message,
            ], function ($mail) use ($subject, $pdfContent, $quote) {
                $mail->to($this->recipientEmail, $this->recipientName)
                    ->subject($subject)
                    ->attachData($pdfContent, "Orcamento-{$quote->quote_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            QuoteEmail::where('id', $this->emailLogId)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            AuditLog::log('email_sent', "E-mail com orçamento {$quote->quote_number} enviado para {$this->recipientEmail}", $quote);
        } catch (\Throwable $e) {
            Log::error("SendQuoteEmailJob: falha ao enviar orçamento #{$this->quoteId}", [
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
            ]);

            QuoteEmail::where('id', $this->emailLogId)->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        // Callback de falha sem binding — scope de tenant ausente é esperado.
        // Bind ocorre após carregar (linha seguinte).
        $quote = Quote::withoutGlobalScope('tenant')->find($this->quoteId);
        if ($quote) {
            app()->instance('current_tenant_id', $quote->tenant_id);
        }

        QuoteEmail::where('id', $this->emailLogId)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => Str::limit($e->getMessage(), 1000),
        ]);

        if ($quote) {
            AuditLog::log('email_failed', "Falha ao enviar orçamento {$quote->quote_number} para {$this->recipientEmail}", $quote, null, [
                'recipient_email' => $this->recipientEmail,
                'error' => Str::limit($e->getMessage(), 255),
            ]);
        }

        Log::error('SendQuoteEmailJob failed', ['quote_id' => $this->quoteId, 'error' => $e->getMessage()]);
    }
}
