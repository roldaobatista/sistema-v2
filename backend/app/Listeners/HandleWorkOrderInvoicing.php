<?php

namespace App\Listeners;

use App\Enums\QuoteStatus;
use App\Enums\StockMovementType;
use App\Events\WorkOrderInvoiced;
use App\Jobs\DispatchWebhookJob;
use App\Jobs\EmitFiscalNoteJob;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\CommissionRule;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StockMovement;
use App\Models\SystemAlert;
use App\Models\SystemSetting;
use App\Models\Webhook;
use App\Models\WorkOrder;
use App\Services\CommissionService;
use App\Services\InvoicingService;
use App\Services\StockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleWorkOrderInvoicing implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private InvoicingService $invoicingService,
        private StockService $stockService,
        private CommissionService $commissionService,
    ) {}

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(WorkOrderInvoiced $event, \Throwable $exception): void
    {
        $wo = $event->workOrder;

        Log::critical("HandleWorkOrderInvoicing FAILED definitivamente para OS #{$wo->id}", [
            'error' => $exception->getMessage(),
        ]);

        try {
            // Reverter OS para DELIVERED se ainda estiver INVOICED
            if ($wo->fresh()?->status === WorkOrder::STATUS_INVOICED) {
                $wo->forceFill(['status' => WorkOrder::STATUS_DELIVERED])->saveQuietly();
                $wo->statusHistory()->create([
                    'tenant_id' => $wo->tenant_id,
                    'user_id' => $event->user?->id,
                    'from_status' => WorkOrder::STATUS_INVOICED,
                    'to_status' => WorkOrder::STATUS_DELIVERED,
                    'notes' => "Faturamento revertido automaticamente após falha definitiva: {$exception->getMessage()}",
                ]);
            }

            Notification::notify(
                $wo->tenant_id,
                $event->user?->id ?? $wo->created_by,
                'invoicing_failed',
                'Faturamento falhou',
                [
                    'message' => "O faturamento da OS {$wo->business_number} falhou após 3 tentativas: {$exception->getMessage()}. A OS foi revertida para Entregue.",
                    'icon' => 'x-circle',
                    'color' => 'danger',
                    'data' => ['work_order_id' => $wo->id],
                ]
            );
        } catch (\Throwable $e) {
            Log::emergency("HandleWorkOrderInvoicing: falha ao reverter OS #{$wo->id} após failure", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handle(WorkOrderInvoiced $event): void
    {
        $wo = $event->workOrder;
        $user = $event->user;

        if (! $wo->tenant_id) {
            Log::error('HandleWorkOrderInvoicing: OS sem tenant_id, ignorando', ['wo_id' => $wo->id]);

            return;
        }

        app()->instance('current_tenant_id', $wo->tenant_id);

        try {
            $wo->statusHistory()->create([
                'tenant_id' => $wo->tenant_id,
                'user_id' => $user->id,
                'from_status' => $event->fromStatus,
                'to_status' => WorkOrder::STATUS_INVOICED,
                'notes' => "OS faturada por {$user->name}",
            ]);
        } catch (\Throwable $e) {
            Log::error("HandleWorkOrderInvoicing: falha ao registrar histórico para OS #{$wo->id}", ['error' => $e->getMessage()]);
        }

        try {
            // Chamamos as services e suas transactions
            $result = $this->invoicingService->generateFromWorkOrder($wo, $user->id);

            $this->deductWorkOrderStock($wo);

        } catch (\Throwable $e) {
            // Tenta resgatar a Invoice gerada/existente antes ou durante a tentativa falha
            // LEI 4 JUSTIFICATIVA: listener executa em handler de evento sem tenant no contexto; busca por work_order_id (que já é tenant-scoped) garante isolamento.
            $invoice = Invoice::withoutGlobalScopes()
                ->where('work_order_id', $wo->id)
                ->where('status', '!=', Invoice::STATUS_CANCELLED)
                ->first();

            if ($invoice) {
                $this->markInvoicingAsFailed($invoice, $wo, $user?->id, $e);
            } else {
                $wo->forceFill(['status' => WorkOrder::STATUS_DELIVERED])->saveQuietly();
                $wo->statusHistory()->create([
                    'tenant_id' => $wo->tenant_id,
                    'user_id' => $user?->id,
                    'from_status' => WorkOrder::STATUS_INVOICED,
                    'to_status' => WorkOrder::STATUS_DELIVERED,
                    'notes' => "Faturamento revertido automaticamente por falha: {$e->getMessage()}",
                ]);
            }

            throw $e;
        }

        try {
            $this->commissionService->calculateAndGenerate($wo, CommissionRule::WHEN_OS_INVOICED);
        } catch (\Throwable $e) {
            // ERRO (não warning) — comissão é operação financeira crítica
            Log::error('Falha ao gerar comissões no faturamento da OS', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Criar alerta de sistema visível no dashboard
            try {
                SystemAlert::create([
                    'tenant_id' => $wo->tenant_id,
                    'type' => 'commission_failure',
                    'severity' => 'high',
                    'title' => "Comissões não geradas para OS {$wo->business_number}",
                    'message' => "A OS foi faturada mas as comissões não puderam ser calculadas: {$e->getMessage()}. Verifique as regras de comissão e gere manualmente.",
                    'metadata' => ['work_order_id' => $wo->id, 'error' => $e->getMessage()],
                    'acknowledged' => false,
                ]);
            } catch (\Throwable $alertError) {
                Log::error('Falha ao criar alerta de comissão', ['error' => $alertError->getMessage()]);
            }

            // Notificar todos os admins do tenant (não só o usuário que faturou)
            if ($user?->id) {
                Notification::notify(
                    $wo->tenant_id,
                    $user->id,
                    'commission_error',
                    'Falha ao gerar comissões',
                    [
                        'message' => "As comissões da OS {$wo->business_number} não foram geradas automaticamente: {$e->getMessage()}. Verifique manualmente.",
                        'icon' => 'alert-triangle',
                        'color' => 'danger',
                        'data' => ['work_order_id' => $wo->id],
                    ]
                );
            }
        }

        try {
            $this->triggerFiscalNoteEmission($wo, $result['invoice']);
        } catch (\Throwable $e) {
            Log::warning("Falha ao iniciar emissao de nota fiscal para OS #{$wo->business_number}", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            if ($wo->created_by) {
                $installmentInfo = count($result['receivables'] ?? []) > 1
                    ? ' ('.count($result['receivables']).' parcelas)'
                    : '';

                Notification::notify(
                    $wo->tenant_id,
                    $wo->created_by,
                    'os_invoiced',
                    'OS Faturada',
                    [
                        'message' => "A OS {$wo->business_number} (R$ ".number_format((float) $wo->total, 2, ',', '.').") foi faturada{$installmentInfo}. Fatura {$result['invoice']->invoice_number} gerada.",
                        'icon' => 'receipt',
                        'color' => 'success',
                        'data' => [
                            'work_order_id' => $wo->id,
                            'invoice_id' => $result['invoice']->id,
                            'account_receivable_id' => $result['ar']?->id,
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("HandleWorkOrderInvoicing: notificação falhou para OS #{$wo->business_number}", ['error' => $e->getMessage()]);
        }

        try {
            $this->syncQuoteStatus($wo, Quote::STATUS_INVOICED, "Orçamento faturado automaticamente após faturamento da OS {$wo->business_number}");
        } catch (\Throwable $e) {
            Log::warning("HandleWorkOrderInvoicing: syncQuoteStatus falhou para OS #{$wo->business_number}", ['error' => $e->getMessage()]);
        }

        // Disparar webhooks
        try {
            $this->dispatchWebhook($wo, 'work_order.invoiced');
        } catch (\Throwable $e) {
            Log::warning("Webhook dispatch failed for OS #{$wo->id}", ['error' => $e->getMessage()]);
        }
    }

    private function dispatchWebhook(WorkOrder $wo, string $event): void
    {
        $webhooks = Webhook::where('tenant_id', $wo->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) use ($event) {
                $q->whereJsonContains('events', $event)
                    ->orWhereJsonContains('events', '*');
            })
            ->get();

        foreach ($webhooks as $webhook) {
            DispatchWebhookJob::dispatch($webhook, $event, [
                'work_order_id' => $wo->id,
                'business_number' => $wo->business_number,
                'status' => $wo->status,
                'customer_id' => $wo->customer_id,
                'total' => $wo->total,
                'updated_at' => $wo->updated_at->toIso8601String(),
            ])->onQueue('webhooks');
        }
    }

    private function triggerFiscalNoteEmission(WorkOrder $wo, Invoice $invoice): void
    {
        $autoEmit = SystemSetting::where('tenant_id', $wo->tenant_id)
            ->where('key', 'fiscal_auto_emit')
            ->value('value');

        if (! $autoEmit) {
            return;
        }

        $hasServices = $wo->items()->where('type', 'service')->exists();
        $hasProducts = $wo->items()->where('type', 'product')->exists();

        if ($hasServices) {
            EmitFiscalNoteJob::dispatch($wo->tenant_id, $invoice->id, 'nfse')
                ->onQueue('fiscal');
        }

        if ($hasProducts) {
            EmitFiscalNoteJob::dispatch($wo->tenant_id, $invoice->id, 'nfe')
                ->onQueue('fiscal');
        }

        Log::info("Emissao fiscal enfileirada para OS #{$wo->business_number}", [
            'nfse' => $hasServices,
            'nfe' => $hasProducts,
        ]);
    }

    private function deductWorkOrderStock(WorkOrder $wo): void
    {
        $productItems = $wo->items()
            ->where('type', 'product')
            ->whereNotNull('reference_id')
            ->get();

        $productIds = $productItems->pluck('reference_id')->unique()->filter();
        $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($productItems as $item) {
            $product = $productsById->get($item->reference_id);
            if ($product) {
                $warehouseId = $item->warehouse_id ?: $this->stockService->resolveWarehouseIdForWorkOrder($wo);
                $requestedQuantity = (float) $item->quantity;
                $alreadyReserved = $this->sumExistingStockMovement(
                    $wo,
                    $product->id,
                    $warehouseId,
                    StockMovementType::Reserve->value,
                );
                $alreadyReturned = $this->sumExistingStockMovement(
                    $wo,
                    $product->id,
                    $warehouseId,
                    StockMovementType::Return->value,
                );
                $alreadyDeducted = $this->sumExistingStockMovement(
                    $wo,
                    $product->id,
                    $warehouseId,
                    StockMovementType::Exit->value,
                    "OS-{$wo->number} (faturamento)",
                );

                $netReserved = bcsub((string) $alreadyReserved, (string) $alreadyReturned, 4);
                if (bccomp($netReserved, '0', 4) < 0) {
                    $netReserved = '0';
                }
                $accountedQuantity = bcadd($netReserved, (string) $alreadyDeducted, 4);
                $missingQuantity = bcsub((string) $requestedQuantity, $accountedQuantity, 4);

                if (bccomp($missingQuantity, '0', 4) <= 0) {
                    continue;
                }

                $this->stockService->deduct($product, (float) $missingQuantity, $wo, $warehouseId);
            }
        }
    }

    private function sumExistingStockMovement(
        WorkOrder $workOrder,
        int $productId,
        ?int $warehouseId,
        string $type,
        ?string $reference = null,
    ): float {
        // LEI 4 JUSTIFICATIVA: listener roda fora do request cycle; tenant_id do workOrder filtrado explicitamente abaixo.
        return (float) StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $workOrder->tenant_id)
            ->where('work_order_id', $workOrder->id)
            ->where('product_id', $productId)
            ->where('type', $type)
            ->when($warehouseId !== null, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($reference !== null, fn ($query) => $query->where('reference', $reference))
            ->sum('quantity');
    }

    private function markInvoicingAsFailed(Invoice $invoice, WorkOrder $wo, ?int $userId, \Throwable $exception): void
    {
        DB::transaction(function () use ($invoice, $wo, $userId, $exception): void {
            $invoice->updateQuietly([
                'status' => Invoice::STATUS_CANCELLED,
                'fiscal_status' => Invoice::FISCAL_STATUS_FAILED,
                'fiscal_error' => "Baixa de estoque obrigatoria falhou: {$exception->getMessage()}",
            ]);

            // LEI 4 JUSTIFICATIVA: listener sem tenant no contexto; invoice_id já é tenant-scoped e whereNull('deleted_at') preserva semântica do soft-delete.
            AccountReceivable::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->whereNull('deleted_at')
                ->update([
                    'status' => AccountReceivable::STATUS_CANCELLED,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            $wo->forceFill([
                'status' => WorkOrder::STATUS_DELIVERED,
            ])->saveQuietly();

            $wo->statusHistory()->create([
                'tenant_id' => $wo->tenant_id,
                'user_id' => $userId,
                'from_status' => WorkOrder::STATUS_INVOICED,
                'to_status' => WorkOrder::STATUS_DELIVERED,
                'notes' => "Faturamento revertido automaticamente para Entregue por falha na baixa de estoque: {$exception->getMessage()}",
            ]);

            $this->syncQuoteStatus($wo, Quote::STATUS_IN_EXECUTION, "Orçamento revertido para em execução após falha no faturamento da OS {$wo->business_number}");
        });
    }

    private function syncQuoteStatus(WorkOrder $workOrder, string $targetStatus, string $description): void
    {
        if (! $workOrder->quote_id) {
            return;
        }

        $quote = Quote::query()
            ->where('tenant_id', $workOrder->tenant_id)
            ->find($workOrder->quote_id);

        if (! $quote) {
            return;
        }

        $currentStatus = $quote->status instanceof QuoteStatus
            ? $quote->status->value
            : (string) $quote->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        $quote->updateQuietly(['status' => $targetStatus]);

        AuditLog::log(
            'status_changed',
            "{$description} ({$quote->quote_number})",
            $quote,
            ['status' => $currentStatus],
            ['status' => $targetStatus]
        );
    }
}
