<?php

namespace App\Listeners;

use App\Events\WorkOrderCancelled;
use App\Jobs\DispatchWebhookJob;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Webhook;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\StockService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleWorkOrderCancellation implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private StockService $stockService,
    ) {}

    public function failed(WorkOrderCancelled $event, \Throwable $exception): void
    {
        Log::critical("HandleWorkOrderCancellation FAILED definitivamente para OS #{$event->workOrder->id}", [
            'error' => $exception->getMessage(),
        ]);

        try {
            Notification::notify(
                $event->workOrder->tenant_id,
                $event->user?->id ?? $event->workOrder->created_by,
                'cancellation_failed',
                'Processamento de cancelamento falhou',
                [
                    'message' => "O processamento pós-cancelamento da OS {$event->workOrder->business_number} falhou após 3 tentativas: {$exception->getMessage()}. Estoque e financeiro podem estar inconsistentes.",
                    'icon' => 'x-circle',
                    'color' => 'danger',
                    'data' => ['work_order_id' => $event->workOrder->id],
                ]
            );
        } catch (\Throwable $e) {
            Log::emergency("HandleWorkOrderCancellation: falha ao notificar sobre failure da OS #{$event->workOrder->id}");
        }
    }

    public function handle(WorkOrderCancelled $event): void
    {
        $wo = $event->workOrder;
        $user = $event->user;

        if (! $wo->tenant_id) {
            Log::error('HandleWorkOrderCancellation: OS sem tenant_id, ignorando', ['wo_id' => $wo->id]);

            return;
        }

        app()->instance('current_tenant_id', $wo->tenant_id);

        try {
            $wo->statusHistory()->create([
                'tenant_id' => $wo->tenant_id,
                'user_id' => $user->id,
                'from_status' => $event->fromStatus,
                'to_status' => WorkOrder::STATUS_CANCELLED,
                'notes' => "OS cancelada por {$user->name}. Motivo: {$event->reason}",
            ]);
        } catch (\Throwable $e) {
            Log::error("HandleWorkOrderCancellation: falha ao registrar histórico para OS #{$wo->id}", ['error' => $e->getMessage()]);
        }

        try {
            if ($wo->created_by) {
                Notification::notify(
                    $wo->tenant_id,
                    $wo->created_by,
                    'os_cancelled',
                    'OS Cancelada',
                    [
                        'message' => "A OS {$wo->business_number} foi cancelada. Motivo: {$event->reason}",
                        'icon' => 'x-circle',
                        'color' => 'danger',
                        'data' => ['work_order_id' => $wo->id, 'reason' => $event->reason],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("HandleWorkOrderCancellation: notificação falhou para OS #{$wo->business_number}", ['error' => $e->getMessage()]);
        }

        // Guard de idempotência robusto: usa lock para evitar devolução dupla de estoque
        $lockKey = "wo_cancellation_{$wo->id}";
        $lock = Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            Log::error("HandleWorkOrderCancellation: lock não adquirido para OS #{$wo->id}, possível execução duplicada — abortando para evitar devolução dupla de estoque");

            return;
        }

        try {
            // Verificar se já houve cancelamento processado (>1 pois o registro ATUAL já foi criado acima)
            $alreadyCancelled = $wo->statusHistory()
                ->where('to_status', WorkOrder::STATUS_CANCELLED)
                ->count() > 1;

            if ($alreadyCancelled) {
                return;
            }

            DB::transaction(function () use ($wo) {
                // Devolver estoque reservado para itens tipo produto
                $productItems = $wo->items()->where('type', WorkOrderItem::TYPE_PRODUCT)->whereNotNull('reference_id')->get();
                $productIds = $productItems->pluck('reference_id')->unique()->filter();
                $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');

                foreach ($productItems as $item) {
                    $product = $productsById->get($item->reference_id);
                    if ($product) {
                        $this->stockService->returnStock($product, (float) $item->quantity, $wo);
                    }
                }

                // Estornar comissões pendentes/aprovadas vinculadas à OS
                $isSqlite = DB::connection()->getDriverName() === 'sqlite';
                $cancelNote = ' | Estornado: OS cancelada em '.now()->format('d/m/Y H:i');

                $concatExpr = $isSqlite
                    ? "COALESCE(notes, '') || ".DB::connection()->getPdo()->quote($cancelNote)
                    : "CONCAT(COALESCE(notes, ''), ".DB::connection()->getPdo()->quote($cancelNote).')';

                $reversedCount = CommissionEvent::where('tenant_id', $wo->tenant_id)
                    ->where('work_order_id', $wo->id)
                    ->whereIn('status', [CommissionEvent::STATUS_PENDING, CommissionEvent::STATUS_APPROVED])
                    ->update([
                        'status' => CommissionEvent::STATUS_REVERSED,
                        'notes' => DB::raw($concatExpr),
                    ]);

                if ($reversedCount > 0) {
                    Log::info("OS #{$wo->business_number} cancelada: {$reversedCount} comissões estornadas");
                }

                // Cancelar Invoice e AccountReceivable vinculados (se OS já faturada)
                $cancelInvoiceNote = ' | Cancelada: OS cancelada em '.now()->format('d/m/Y H:i');
                $concatInvExpr = $isSqlite
                    ? "COALESCE(observations, '') || ".DB::connection()->getPdo()->quote($cancelInvoiceNote)
                    : "CONCAT(COALESCE(observations, ''), ".DB::connection()->getPdo()->quote($cancelInvoiceNote).')';

                $invoicesCancelled = Invoice::where('tenant_id', $wo->tenant_id)
                    ->where('work_order_id', $wo->id)
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->update([
                        'status' => Invoice::STATUS_CANCELLED,
                        'observations' => DB::raw($concatInvExpr),
                    ]);

                $cancelArNote = ' | Cancelado: OS cancelada em '.now()->format('d/m/Y H:i');
                $concatArExpr = $isSqlite
                    ? "COALESCE(notes, '') || ".DB::connection()->getPdo()->quote($cancelArNote)
                    : "CONCAT(COALESCE(notes, ''), ".DB::connection()->getPdo()->quote($cancelArNote).')';

                $arCancelled = AccountReceivable::where('tenant_id', $wo->tenant_id)
                    ->where('work_order_id', $wo->id)
                    ->whereNotIn('status', [AccountReceivable::STATUS_PAID, AccountReceivable::STATUS_CANCELLED])
                    ->update([
                        'status' => AccountReceivable::STATUS_CANCELLED,
                        'notes' => DB::raw($concatArExpr),
                    ]);

                if ($invoicesCancelled > 0 || $arCancelled > 0) {
                    Log::info("OS #{$wo->business_number} cancelada: {$invoicesCancelled} fatura(s) e {$arCancelled} conta(s) a receber canceladas");
                }
            });
        } catch (\Throwable $e) {
            Log::error("HandleWorkOrderCancellation: falha na reversão para OS #{$wo->business_number}", ['error' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        // Disparar webhooks
        try {
            $this->dispatchWebhook($wo, 'work_order.cancelled');
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
}
