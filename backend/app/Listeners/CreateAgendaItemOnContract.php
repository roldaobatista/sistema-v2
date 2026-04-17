<?php

namespace App\Listeners;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemType;
use App\Events\ContractRenewing;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateAgendaItemOnContract implements ShouldQueue
{
    public function handle(ContractRenewing $event): void
    {
        $contract = $event->contract;

        app()->instance('current_tenant_id', $contract->tenant_id);

        try {
            $responsavel = $contract->assigned_to ?? $contract->created_by;

            AgendaItem::criarDeOrigem(
                model: $contract,
                tipo: AgendaItemType::CONTRATO,
                title: "Contrato renovando — {$contract->customer?->name}",
                responsavelId: $responsavel,
                extras: [
                    'priority' => AgendaItemPriority::MEDIA,
                    'due_at' => $contract->end_date,
                    'short_description' => "Contrato #{$contract->id} expira em breve",
                    'context' => [
                        'contrato_id' => $contract->id,
                        'cliente' => $contract->customer?->name,
                        'renovacao' => $contract->end_date?->toDateString(),
                        'link' => "/contratos/{$contract->id}",
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CreateAgendaItemOnContract failed', ['contract_id' => $contract->id, 'error' => $e->getMessage()]);
        }
    }
}
