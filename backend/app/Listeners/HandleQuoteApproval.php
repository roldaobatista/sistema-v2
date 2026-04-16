<?php

namespace App\Listeners;

use App\Events\QuoteApproved;
use App\Models\CrmActivity;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

class HandleQuoteApproval implements ShouldQueueAfterCommit
{
    public function handle(QuoteApproved $event): void
    {
        $quote = $event->quote;
        $user = $event->user;

        app()->instance('current_tenant_id', $quote->tenant_id);

        try {
            $quoteNumber = $quote->quote_number;
            $recipientId = $quote->seller_id ?? $user?->id;

            $approvalChannel = $quote->approval_channel ?: 'internal';
            $approvedByName = $quote->approved_by_name ?: ($user?->name ?? 'Sistema');
            $approvalMessage = match ($approvalChannel) {
                'portal' => "Aprovado pelo cliente via portal por {$approvedByName}",
                'magic_link' => "Aprovado pelo cliente via link público por {$approvedByName}",
                default => "Aprovado por {$approvedByName}",
            };

            if ($quote->customer_id) {
                try {
                    $this->storeApprovalActivity($quote, $approvalChannel, $approvedByName, $approvalMessage, $user?->id);
                } catch (\Throwable $e) {
                    Log::error('HandleQuoteApproval: CrmActivity failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
                }
            }

            try {
                $this->storeApprovalNotification($quote, $recipientId, $approvalChannel, $approvedByName, $approvalMessage);
            } catch (\Throwable $e) {
                Log::error('HandleQuoteApproval: Notification failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            }

            // Enviar email ao vendedor
            try {
                $seller = $quote->seller ?? ($quote->seller_id ? User::find($quote->seller_id) : null);
                if ($seller) {
                    $seller->notify(new QuoteStatusNotification($quote, 'approved'));
                }
            } catch (\Throwable $e) {
                Log::warning('HandleQuoteApproval: Email notification failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error('HandleQuoteApproval failed', ['quote_id' => $quote->id ?? null, 'error' => $e->getMessage()]);
        }
    }

    private function storeApprovalActivity(
        Quote $quote,
        string $approvalChannel,
        string $approvedByName,
        string $approvalMessage,
        ?int $fallbackUserId,
    ): void {
        $attributes = [
            'tenant_id' => $quote->tenant_id,
            'customer_id' => $quote->customer_id,
            'user_id' => $quote->seller_id ?? $fallbackUserId,
            'type' => Quote::ACTIVITY_TYPE_APPROVED,
            'title' => "Orcamento #{$quote->quote_number} aprovado",
            'description' => $approvalMessage.'. Valor: R$ '.number_format((float) $quote->total, 2, ',', '.'),
            'scheduled_at' => now(),
            'completed_at' => now(),
            'channel' => $approvalChannel,
            'metadata' => [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'approval_channel' => $approvalChannel,
                'approved_by_name' => $approvedByName,
                'approved_at' => $quote->approved_at?->toISOString(),
            ],
        ];

        $existingActivity = CrmActivity::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('customer_id', $quote->customer_id)
            ->where('type', Quote::ACTIVITY_TYPE_APPROVED)
            ->where('channel', $approvalChannel)
            ->where('metadata->quote_id', $quote->id)
            ->first();

        if ($existingActivity) {
            $existingActivity->fill($attributes);
            $existingActivity->save();

            return;
        }

        CrmActivity::create($attributes);
    }

    private function storeApprovalNotification(
        Quote $quote,
        ?int $recipientId,
        string $approvalChannel,
        string $approvedByName,
        string $approvalMessage,
    ): void {
        $attributes = [
            'tenant_id' => $quote->tenant_id,
            'user_id' => $recipientId,
            'type' => Quote::ACTIVITY_TYPE_APPROVED,
            'title' => 'Orcamento Aprovado',
            'message' => "O orcamento #{$quote->quote_number} foi aprovado. {$approvalMessage}.",
            'data' => [
                'quote_id' => $quote->id,
                'total' => $quote->total,
                'approval_channel' => $approvalChannel,
                'approved_by_name' => $approvedByName,
            ],
        ];

        $existingNotification = Notification::query()
            ->where('tenant_id', $quote->tenant_id)
            ->where('user_id', $recipientId)
            ->where('type', Quote::ACTIVITY_TYPE_APPROVED)
            ->where('data->quote_id', $quote->id)
            ->first();

        if ($existingNotification) {
            $existingNotification->fill($attributes);
            $existingNotification->save();

            return;
        }

        Notification::create($attributes);
    }
}
