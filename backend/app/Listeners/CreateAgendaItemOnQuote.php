<?php

namespace App\Listeners;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemType;
use App\Events\QuoteApproved;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnQuote implements ShouldQueueAfterCommit
{
    public function handle(QuoteApproved $event): void
    {
        $quote = $event->quote;

        app()->instance('current_tenant_id', $quote->tenant_id);

        try {
            $responsavel = $quote->seller_id ?? $event->user?->id;
            $approvalChannel = $quote->approval_channel ?: 'internal';
            $approvedByName = $quote->approved_by_name ?: $event->user?->name;

            AgendaItem::criarDeOrigem(
                model: $quote,
                tipo: AgendaItemType::ORCAMENTO,
                title: "Orcamento #{$quote->quote_number} aprovado - {$quote->customer?->name}",
                responsavelId: $responsavel,
                extras: [
                    'priority' => AgendaItemPriority::ALTA,
                    'short_description' => 'Valor: R$ '.number_format((float) ($quote->total ?? 0), 2, ',', '.'),
                    'context' => [
                        'numero' => $quote->quote_number,
                        'cliente' => $quote->customer?->name,
                        'valor' => $quote->total,
                        'link' => "/orcamentos/{$quote->id}",
                        'approval_channel' => $approvalChannel,
                        'approved_by_name' => $approvedByName,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnQuote failed', ['quote_id' => $quote->id, 'error' => $e->getMessage()]);
        }
    }
}
