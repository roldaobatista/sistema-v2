<?php

namespace App\Listeners;

use App\Events\WorkOrderCompleted;
use App\Jobs\DispatchWebhookJob;
use App\Models\CommissionRule;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\Webhook;
use App\Models\WorkOrder;
use App\Services\ClientNotificationService;
use App\Services\CommissionService;
use App\Services\CustomerConversionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleWorkOrderCompletion implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private ClientNotificationService $clientNotificationService,
        private CommissionService $commissionService,
        private CustomerConversionService $customerConversionService,
    ) {}

    public function failed(WorkOrderCompleted $event, \Throwable $exception): void
    {
        Log::critical("HandleWorkOrderCompletion FAILED definitivamente para OS #{$event->workOrder->id}", [
            'error' => $exception->getMessage(),
        ]);

        try {
            Notification::notify(
                $event->workOrder->tenant_id,
                $event->user?->id ?? $event->workOrder->created_by,
                'completion_failed',
                'Processamento de conclusão falhou',
                [
                    'message' => "O processamento pós-conclusão da OS {$event->workOrder->business_number} falhou após 3 tentativas: {$exception->getMessage()}",
                    'icon' => 'x-circle',
                    'color' => 'danger',
                    'data' => ['work_order_id' => $event->workOrder->id],
                ]
            );
        } catch (\Throwable $e) {
            Log::emergency("HandleWorkOrderCompletion: falha ao notificar sobre failure da OS #{$event->workOrder->id}");
        }
    }

    public function handle(WorkOrderCompleted $event): void
    {
        $wo = $event->workOrder;
        $user = $event->user;

        if (! $wo->tenant_id) {
            Log::error('HandleWorkOrderCompletion: OS sem tenant_id, ignorando', ['wo_id' => $wo->id]);

            return;
        }

        app()->instance('current_tenant_id', $wo->tenant_id);

        // Registrar no histórico
        try {
            $wo->statusHistory()->create([
                'tenant_id' => $wo->tenant_id,
                'user_id' => $user->id,
                'from_status' => $event->fromStatus,
                'to_status' => WorkOrder::STATUS_COMPLETED,
                'notes' => "OS concluída por {$user->name}",
            ]);
        } catch (\Throwable $e) {
            Log::error("HandleWorkOrderCompletion: falha ao registrar histórico para OS #{$wo->id}", ['error' => $e->getMessage()]);
        }

        // Notificar o responsável / admin (interno)
        try {
            if ($wo->created_by) {
                Notification::notify(
                    $wo->tenant_id,
                    $wo->created_by,
                    'os_completed',
                    'OS Concluída',
                    [
                        'message' => "A OS {$wo->business_number} foi concluída por {$user->name}.",
                        'data' => ['work_order_id' => $wo->id],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("HandleWorkOrderCompletion: notificação interna falhou para OS #{$wo->id}", ['error' => $e->getMessage()]);
        }

        // Gerar comissões (regras com applies_when = os_completed)
        try {
            $this->commissionService->calculateAndGenerate($wo, CommissionRule::WHEN_OS_COMPLETED);
        } catch (\Throwable $e) {
            Log::warning('Falha ao gerar comissões na conclusão da OS', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Recalcular health score do cliente
        try {
            if ($wo->customer) {
                $wo->customer->recalculateHealthScore();
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao recalcular health score na conclusão da OS', [
                'work_order_id' => $wo->id,
                'customer_id' => $wo->customer_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Converter lead CRM → cliente se esta é a 1ª OS concluída
        try {
            $this->customerConversionService->convertLeadIfFirstOS($wo);
        } catch (\Throwable $e) {
            Log::warning('Falha ao converter lead CRM na conclusão da OS', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notificar cliente via email
        try {
            $this->clientNotificationService->notifyOsCompleted($wo);
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar cliente sobre conclusão da OS', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Solicitar assinatura do cliente (se configurado)
        try {
            $requireSignature = SystemSetting::where('tenant_id', $wo->tenant_id)
                ->where('key', 'require_signature_on_completion')
                ->value('value');
            if ($requireSignature && ! $wo->signatures()->exists()) {
                $this->clientNotificationService->notifySignatureRequired($wo);
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao solicitar assinatura do cliente', [
                'work_order_id' => $wo->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Disparar webhooks
        try {
            $this->dispatchWebhook($wo, 'work_order.completed');
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
