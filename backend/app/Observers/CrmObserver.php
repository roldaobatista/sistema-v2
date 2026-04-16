<?php

namespace App\Observers;

use App\Models\CrmActivity;
use App\Models\CrmDeal;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CrmObserver
{
    /**
     * WorkOrder status changed to completed -> log activity + schedule follow-up.
     */
    public function workOrderUpdated(WorkOrder $wo): void
    {
        if (! $wo->wasChanged('status') || ! $wo->customer_id) {
            return;
        }

        $status = $wo->status;
        $fromStatus = $wo->getOriginal('status');
        $statusLabels = WorkOrder::STATUSES;

        // Notify creator + assignee about status change.
        $notifyUserIds = array_unique(array_filter([$wo->created_by, $wo->assigned_to]));
        $toLabel = $statusLabels[$status]['label'] ?? $status;
        $fromLabel = $statusLabels[$fromStatus]['label'] ?? $fromStatus;

        foreach ($notifyUserIds as $uid) {
            try {
                Notification::notify(
                    $wo->tenant_id,
                    $uid,
                    'os_status_changed',
                    "OS {$wo->business_number}: {$toLabel}",
                    [
                        'message' => "Status alterado de {$fromLabel} para {$toLabel}",
                        'icon' => 'file-text',
                        'color' => $statusLabels[$status]['color'] ?? 'info',
                        'link' => "/os/{$wo->id}",
                        'notifiable_type' => WorkOrder::class,
                        'notifiable_id' => $wo->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: notification failed for WO #{$wo->id}, user {$uid}", ['error' => $e->getMessage()]);
            }
        }

        if ($status === WorkOrder::STATUS_COMPLETED) {
            try {
                CrmActivity::logSystemEvent(
                    $wo->tenant_id,
                    $wo->customer_id,
                    "OS {$wo->business_number} concluida",
                    null,
                    $wo->assigned_to,
                    [
                        'work_order_id' => $wo->id,
                        'total' => (float) $wo->total,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: logSystemEvent failed for WO #{$wo->id}", ['error' => $e->getMessage()]);
            }

            // Schedule follow-up activity in 7 days if value > 500.
            try {
                if (bccomp((string) $wo->total, '500', 2) > 0) {
                    CrmActivity::create([
                        'tenant_id' => $wo->tenant_id,
                        'type' => CrmActivity::TYPE_TASK,
                        'customer_id' => $wo->customer_id,
                        'user_id' => $wo->assigned_to ?? $wo->created_by,
                        'title' => "Follow-up pos-servico: OS {$wo->business_number}",
                        'description' => "Ligar para o cliente para verificar satisfacao apos OS concluida (valor: R$ {$wo->total})",
                        'scheduled_at' => now()->addDays(7),
                        'is_automated' => true,
                        'channel' => CrmActivity::CHANNEL_PHONE,
                        'metadata' => ['work_order_id' => $wo->id],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: follow-up creation failed for WO #{$wo->id}", ['error' => $e->getMessage()]);
            }

            // Update last_contact_at + health score.
            try {
                $wo->customer?->update(['last_contact_at' => now()]);
                $wo->customer?->recalculateHealthScore();
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: customer update failed for WO #{$wo->id}", ['error' => $e->getMessage()]);
            }
        }

        if ($status === WorkOrder::STATUS_DELIVERED) {
            try {
                CrmActivity::logSystemEvent(
                    $wo->tenant_id,
                    $wo->customer_id,
                    "OS {$wo->business_number} entregue ao cliente",
                    null,
                    $wo->assigned_to,
                    ['work_order_id' => $wo->id]
                );
                $wo->customer?->recalculateHealthScore();
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: delivered event failed for WO #{$wo->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Quote approved -> mark linked deal as won + log activity.
     * Quote rejected -> log activity "Entender motivo" in 3 days.
     */
    public function quoteUpdated(Quote $quote): void
    {
        if (! $quote->customer_id) {
            return;
        }

        // Quote approved.
        if ($quote->wasChanged('approved_at') && $quote->approved_at) {
            // Find linked open deal and mark as won (filtro explícito de tenant para segurança em queue)
            try {
                $deal = CrmDeal::where('tenant_id', $quote->tenant_id)
                    ->where('quote_id', $quote->id)
                    ->where('status', CrmDeal::STATUS_OPEN)
                    ->first();

                if ($deal) {
                    $deal->markAsWon();

                    CrmActivity::logSystemEvent(
                        $quote->tenant_id,
                        $quote->customer_id,
                        "Deal \"{$deal->title}\" ganho automaticamente (orcamento aprovado)",
                        $deal->id,
                        $quote->seller_id,
                        ['quote_id' => $quote->id, 'deal_id' => $deal->id]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: deal markAsWon failed for quote #{$quote->id}", ['error' => $e->getMessage()]);
            }

            // Update customer last_contact_at.
            try {
                $quote->customer?->update(['last_contact_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: customer update failed for quote #{$quote->id}", ['error' => $e->getMessage()]);
            }
        }

        // Quote rejected.
        if ($quote->wasChanged('rejected_at') && $quote->rejected_at) {
            try {
                CrmActivity::logSystemEvent(
                    $quote->tenant_id,
                    $quote->customer_id,
                    "Orcamento {$quote->quote_number} rejeitado".($quote->rejection_reason ? ": {$quote->rejection_reason}" : ''),
                    null,
                    $quote->seller_id,
                    ['quote_id' => $quote->id, 'reason' => $quote->rejection_reason]
                );
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: logSystemEvent failed for quote rejection #{$quote->id}", ['error' => $e->getMessage()]);
            }

            // Schedule "understand reason" activity in 3 days.
            try {
                CrmActivity::create([
                    'tenant_id' => $quote->tenant_id,
                    'type' => CrmActivity::TYPE_TASK,
                    'customer_id' => $quote->customer_id,
                    'user_id' => $quote->seller_id ?? Auth::id(),
                    'title' => "Entender motivo rejeicao: Orc. {$quote->quote_number}",
                    'description' => 'O orcamento foi rejeitado'.($quote->rejection_reason ? " (motivo: {$quote->rejection_reason})" : '').'. Ligar para entender e tentar reverter.',
                    'scheduled_at' => now()->addDays(3),
                    'is_automated' => true,
                    'channel' => CrmActivity::CHANNEL_PHONE,
                    'metadata' => ['quote_id' => $quote->id],
                ]);
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: follow-up creation failed for rejected quote #{$quote->id}", ['error' => $e->getMessage()]);
            }

            // Find linked deal and mark as lost (filtro explícito de tenant)
            try {
                $deal = CrmDeal::where('tenant_id', $quote->tenant_id)
                    ->where('quote_id', $quote->id)
                    ->where('status', CrmDeal::STATUS_OPEN)
                    ->first();

                if ($deal) {
                    $deal->markAsLost($quote->rejection_reason ?? 'Orcamento rejeitado');
                }
            } catch (\Throwable $e) {
                Log::warning("CrmObserver: deal markAsLost failed for rejected quote #{$quote->id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
