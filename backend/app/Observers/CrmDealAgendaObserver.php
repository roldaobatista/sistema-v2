<?php

namespace App\Observers;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Models\AgendaItem;
use App\Models\CrmDeal;
use Illuminate\Support\Facades\Log;

class CrmDealAgendaObserver
{
    public function created(CrmDeal $deal): void
    {
        try {
            $responsavel = $deal->assigned_to ?? $deal->created_by ?? (auth()->check() ? auth()->id() : null);

            AgendaItem::criarDeOrigem(
                model: $deal,
                tipo: AgendaItemType::TAREFA,
                title: "Deal: {$deal->title} — {$deal->customer?->name}",
                responsavelId: $responsavel,
                extras: [
                    'priority' => $this->mapPriority($deal),
                    'due_at' => $deal->expected_close_date,
                    'short_description' => $deal->value
                        ? 'Valor: R$ '.number_format((float) $deal->value, 2, ',', '.')
                        : null,
                    'tags' => ['crm', 'deal'],
                    'context' => [
                        'deal_id' => $deal->id,
                        'cliente' => $deal->customer?->name,
                        'pipeline' => $deal->pipeline?->name,
                        'stage' => $deal->stage?->name,
                        'valor' => $deal->value,
                        'link' => "/crm/deals/{$deal->id}",
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("CrmDealAgendaObserver: falha ao criar agenda para deal #{$deal->id}", ['error' => $e->getMessage()]);
        }
    }

    public function updated(CrmDeal $deal): void
    {
        if (! $deal->isDirty(['status', 'stage_id', 'assigned_to'])) {
            return;
        }

        try {
            if ($deal->isDirty('status')) {
                $status = match ($deal->status) {
                    'won' => AgendaItemStatus::CONCLUIDO,
                    'lost' => AgendaItemStatus::CANCELADO,
                    default => null,
                };

                if ($status) {
                    AgendaItem::syncFromSource($deal, [
                        'status' => $status,
                        'closed_at' => now(),
                        'closed_by' => auth()->check() ? auth()->id() : ($deal->assigned_to ?? $deal->created_by),
                    ]);

                    return;
                }
            }

            $overrides = [];

            if ($deal->isDirty('stage_id')) {
                $overrides['short_description'] = "Etapa: {$deal->stage?->name}";
                $overrides['context'] = array_merge(
                    $deal->metadata ?? [],
                    ['stage' => $deal->stage?->name]
                );
            }

            if ($deal->isDirty('assigned_to') && $deal->assigned_to) {
                $overrides['assignee_user_id'] = $deal->assigned_to;
            }

            if (! empty($overrides)) {
                AgendaItem::syncFromSource($deal, $overrides);
            }
        } catch (\Throwable $e) {
            Log::warning("CrmDealAgendaObserver: falha ao sincronizar agenda para deal #{$deal->id}", ['error' => $e->getMessage()]);
        }
    }

    private function mapPriority(CrmDeal $deal): AgendaItemPriority
    {
        $value = (float) ($deal->value ?? 0);

        if ($value >= 50000) {
            return AgendaItemPriority::URGENTE;
        }
        if ($value >= 10000) {
            return AgendaItemPriority::ALTA;
        }

        return AgendaItemPriority::MEDIA;
    }
}
