<?php

namespace App\Jobs;

use App\Jobs\Middleware\SetTenantContext;
use App\Models\Invoice;
use App\Services\Fiscal\FiscalProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmitFiscalNoteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 60;

    public int $uniqueFor = 900;

    public function __construct(
        public int $tenantId,
        public int $invoiceId,
        public string $noteType,
    ) {
        $this->queue = 'fiscal';
    }

    public function middleware(): array
    {
        return [new SetTenantContext($this->tenantId)];
    }

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->invoiceId}:{$this->noteType}";
    }

    public function handle(FiscalProvider $fiscalProvider): void
    {
        $lock = Cache::lock("fiscal_emit:{$this->uniqueId()}", 900);
        if (! $lock->get()) {
            Log::warning("EmitFiscalNoteJob: duplicate execution blocked for {$this->uniqueId()}");

            return;
        }

        try {
            $invoice = Invoice::with(['workOrder', 'workOrder.customer', 'workOrder.tenant'])->find($this->invoiceId);
            $invoiceStatus = $invoice?->status?->value ?? $invoice?->status;
            if (! $invoice || $invoiceStatus === Invoice::STATUS_CANCELLED) {
                Log::info("EmitFiscalNoteJob: Invoice #{$this->invoiceId} not found or cancelled, skipping.");

                return;
            }

            $wo = $invoice->workOrder;
            if (! $wo) {
                Log::warning("EmitFiscalNoteJob: Invoice #{$this->invoiceId} has no associated work order.");

                return;
            }

            $invoice->updateQuietly(['fiscal_status' => Invoice::FISCAL_STATUS_EMITTING]);

            try {
                $data = [
                    'tenant_id' => $this->tenantId,
                    'invoice_id' => $this->invoiceId,
                    'work_order_id' => $wo->id,
                    'customer_id' => $wo->customer_id,
                    'items' => $invoice->items ?? [],
                    'total' => (float) $invoice->total,
                    'discount' => (float) ($invoice->discount ?? 0),
                ];

                $result = match ($this->noteType) {
                    'nfse' => $fiscalProvider->emitirNFSe($data),
                    'nfe' => $fiscalProvider->emitirNFe($data),
                    default => throw new \InvalidArgumentException("Unknown note type: {$this->noteType}"),
                };

                $invoice->update([
                    'fiscal_status' => Invoice::FISCAL_STATUS_EMITTED,
                    'fiscal_note_key' => $result->reference ?? null,
                    'fiscal_emitted_at' => now(),
                    'fiscal_error' => null,
                ]);

                Log::info("EmitFiscalNoteJob: {$this->noteType} emitida com sucesso", [
                    'invoice_id' => $this->invoiceId,
                    'referencia' => $result->reference ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error("EmitFiscalNoteJob: Falha na emissao {$this->noteType}", [
                    'invoice_id' => $this->invoiceId,
                    'attempt' => $this->attempts(),
                    'error' => $e->getMessage(),
                ]);

                $invoice->updateQuietly(['fiscal_error' => $e->getMessage()]);

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $invoice = Invoice::find($this->invoiceId);
        if ($invoice) {
            $invoice->updateQuietly([
                'fiscal_status' => Invoice::FISCAL_STATUS_FAILED,
                'fiscal_error' => "Falha definitiva apos {$this->tries} tentativas: {$exception->getMessage()}",
            ]);
        }

        Log::error("EmitFiscalNoteJob: falha definitiva {$this->noteType} apos {$this->tries} tentativas", [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
