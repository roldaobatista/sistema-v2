<?php

namespace App\Console\Commands;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AgendaItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScanOverdueFinancials extends Command
{
    protected $signature = 'central:scan-financials';

    protected $description = 'Varre contas a receber/pagar vencidas ou próximas do vencimento e cria AgendaItems';

    public function handle(): int
    {
        $tenants = Tenant::where('status', Tenant::STATUS_ACTIVE)->get();
        $created = 0;

        foreach ($tenants as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $created += $this->processReceivables($tenant);
                $created += $this->processPayables($tenant);
            } catch (\Throwable $e) {
                Log::error("ScanOverdueFinancials: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info("Agenda: {$created} itens financeiros criados/atualizados");

        return self::SUCCESS;
    }

    private function processReceivables(Tenant $tenant): int
    {
        $count = 0;
        $limiteAlerta = now()->addDays(3);

        // Busca recebíveis pendentes com vencimento nos próximos 3 dias ou já vencidos
        $receivables = AccountReceivable::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', AccountReceivable::STATUS_PAID)
            ->where(function ($q) use ($limiteAlerta) {
                $q->where('due_date', '<=', $limiteAlerta)
                    ->whereNotNull('due_date');
            })
            ->with('customer:id,name')
            ->get();

        foreach ($receivables as $rec) {
            try {
                $existing = AgendaItem::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ref_tipo', AccountReceivable::class)
                    ->where('ref_id', $rec->id)
                    ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
                    ->first();

                if ($existing) {
                    // Atualizar prioridade conforme proximidade
                    $existing->update(['prioridade' => $this->calcularPrioridade($rec->due_date)]);
                    continue;
                }

                AgendaItem::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'tipo' => AgendaItemType::FINANCEIRO,
                    'origem' => AgendaItemOrigin::JOB,
                    'ref_tipo' => AccountReceivable::class,
                    'ref_id' => $rec->id,
                    'titulo' => 'Recebível vencendo — R$ '.number_format($rec->amount ?? 0, 2, ',', '.'),
                    'descricao_curta' => "Cliente: {$rec->customer?->name} | Vence: {$rec->due_date?->format('d/m/Y')}",
                    'responsavel_user_id' => $rec->created_by ?? $rec->user_id ?? 1,
                    'criado_por_user_id' => 0, // Sistema
                    'status' => AgendaItemStatus::ABERTO,
                    'prioridade' => $this->calcularPrioridade($rec->due_date),
                    'visibilidade' => AgendaItemVisibility::EQUIPE,
                    'due_at' => $rec->due_date,
                    'contexto' => [
                        'tipo' => 'recebivel',
                        'valor' => $rec->amount,
                        'cliente' => $rec->customer?->name,
                        'link' => '/financeiro/receber',
                    ],
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("ScanOverdueFinancials: falha ao processar receivable #{$rec->id}", ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    private function processPayables(Tenant $tenant): int
    {
        $count = 0;
        $limiteAlerta = now()->addDays(3);

        $payables = AccountPayable::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', '!=', AccountPayable::STATUS_PAID)
            ->where(function ($q) use ($limiteAlerta) {
                $q->where('due_date', '<=', $limiteAlerta)
                    ->whereNotNull('due_date');
            })
            ->with('supplierRelation:id,name')
            ->get();

        foreach ($payables as $pay) {
            try {
                $existing = AgendaItem::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('ref_tipo', AccountPayable::class)
                    ->where('ref_id', $pay->id)
                    ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
                    ->first();

                if ($existing) {
                    $existing->update(['prioridade' => $this->calcularPrioridade($pay->due_date)]);
                    continue;
                }

                AgendaItem::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'tipo' => AgendaItemType::FINANCEIRO,
                    'origem' => AgendaItemOrigin::JOB,
                    'ref_tipo' => AccountPayable::class,
                    'ref_id' => $pay->id,
                    'titulo' => 'Conta a pagar vencendo — R$ '.number_format($pay->amount ?? 0, 2, ',', '.'),
                    'descricao_curta' => "Fornecedor: {$pay->supplierRelation?->name} | Vence: {$pay->due_date?->format('d/m/Y')}",
                    'responsavel_user_id' => $pay->created_by ?? $pay->user_id ?? 1,
                    'criado_por_user_id' => 0,
                    'status' => AgendaItemStatus::ABERTO,
                    'prioridade' => $this->calcularPrioridade($pay->due_date),
                    'visibilidade' => AgendaItemVisibility::EQUIPE,
                    'due_at' => $pay->due_date,
                    'contexto' => [
                        'tipo' => 'pagavel',
                        'valor' => $pay->amount,
                        'fornecedor' => $pay->supplierRelation?->name,
                        'link' => '/financeiro/pagar',
                    ],
                ]);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("ScanOverdueFinancials: falha ao processar payable #{$pay->id}", ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    private function calcularPrioridade($dueDate): AgendaItemPriority
    {
        if (! $dueDate) {
            return AgendaItemPriority::MEDIA;
        }

        $dias = now()->diffInDays($dueDate, false);

        if ($dias < 0) {
            return AgendaItemPriority::URGENTE; // já venceu
        }
        if ($dias <= 1) {
            return AgendaItemPriority::ALTA; // vence hoje ou amanhã
        }

        return AgendaItemPriority::MEDIA; // vence em 2-3 dias
    }
}
