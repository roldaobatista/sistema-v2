<?php

namespace App\Services;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Models\AgendaItem;
use App\Models\AgendaRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgendaAutomationService
{
    /**
     * Aplica regras de automação quando um AgendaItem é criado.
     */
    public function aplicarRegras(AgendaItem $item): void
    {
        $rules = AgendaRule::where('tenant_id', $item->tenant_id)
            ->ativas()
            ->where(function ($q) use ($item) {
                $itemType = $item->typeEnum()?->value;

                $q->whereNull('item_type')
                    ->orWhere('item_type', $itemType);
            })
            ->get();

        foreach ($rules as $rule) {
            try {
                $this->executarRegra($rule, $item);
            } catch (\Throwable $e) {
                Log::warning("Agenda: Regra #{$rule->id} falhou para item #{$item->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Executa uma regra sobre um AgendaItem.
     */
    protected function executarRegra(AgendaRule $rule, AgendaItem $item): void
    {
        // Verificar filtro de priority mínima
        if ($rule->min_priority) {
            $ordemPrioridade = ['low' => 1, 'medium' => 2, 'high' => 3, 'urgent' => 4];
            $minimo = $ordemPrioridade[strtolower((string) $rule->min_priority)] ?? 0;
            $currentPriority = $item->priorityEnum();
            $atual = $ordemPrioridade[strtolower($currentPriority instanceof AgendaItemPriority ? $currentPriority->value : 'medium')] ?? 0;

            if ($atual < $minimo) {
                return;
            }
        }

        match ($rule->action_type) {
            'auto_assign' => $this->acaoAutoAssign($rule, $item),
            'set_priority' => $this->acaoSetPriority($rule, $item),
            'set_due' => $this->acaoSetDue($rule, $item),
            'notify' => $this->acaoNotify($rule, $item),
            default => null,
        };
    }

    /**
     * Auto-atribuir para um usuário ou role.
     */
    protected function acaoAutoAssign(AgendaRule $rule, AgendaItem $item): void
    {
        if ($item->assignee_user_id) {
            return; // Já tem responsável
        }

        if ($rule->assignee_user_id) {
            $item->update(['assignee_user_id' => $rule->assignee_user_id]);
            $item->registrarHistorico('auto_assign', null, $rule->assignee_user_id);

            return;
        }

        // Se tem role_alvo, pega o user com menos itens abertos
        if ($rule->target_role) {
            $userId = $this->encontrarUserMenosOcupado(
                $item->tenant_id,
                $rule->target_role
            );

            if ($userId) {
                $item->update(['assignee_user_id' => $userId]);
                $item->registrarHistorico('auto_assign', null, $userId);
            }
        }
    }

    /**
     * Definir priority automaticamente.
     */
    protected function acaoSetPriority(AgendaRule $rule, AgendaItem $item): void
    {
        $config = $rule->action_config ?? [];
        $priority = isset($config['priority']) ? strtolower((string) $config['priority']) : null;

        if ($priority && AgendaItemPriority::tryFrom($priority)) {
            $oldPriority = $item->priorityEnum()?->value;
            $item->update(['priority' => $priority]);
            $item->registrarHistorico('set_priority', $oldPriority, $priority);
        }
    }

    /**
     * Auto-definir prazo com base na configuração.
     */
    protected function acaoSetDue(AgendaRule $rule, AgendaItem $item): void
    {
        if ($item->due_at) {
            return;
        }

        $config = $rule->action_config ?? [];
        $horas = $config['horas'] ?? null;

        if ($horas) {
            $dueAt = now()->addHours((int) $horas);
            $item->update(['due_at' => $dueAt]);
            $item->registrarHistorico('set_due', null, $dueAt->toDateTimeString());
        }
    }

    /**
     * Enviar notificação para responsável.
     */
    protected function acaoNotify(AgendaRule $rule, AgendaItem $item): void
    {
        $item->gerarNotificacao();
    }

    /**
     * Encontra o user de um role com menos itens abertos na Agenda.
     */
    protected function encontrarUserMenosOcupado(int $tenantId, string $role): ?int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role)
            ->where('model_has_roles.model_type', User::class)
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('users.tenant_id', $tenantId)
            ->where('users.is_active', true)
            ->select('users.id')
            ->selectRaw('(
                SELECT COUNT(*) FROM central_items
                WHERE central_items.assignee_user_id = users.id
                AND central_items.status IN (?, ?)
                AND central_items.tenant_id = ?
            ) as open_count', [
                AgendaItemStatus::ABERTO->value,
                AgendaItemStatus::EM_ANDAMENTO->value,
                $tenantId,
            ])
            ->orderBy('open_count')
            ->first()?->id;
    }

    // ────────────────────────────────────────────────
    // Métodos de consulta gerencial
    // ────────────────────────────────────────────────

    /**
     * KPIs gerais da Agenda para o tenant.
     */
    public function kpis(int $tenantId): array
    {
        $base = AgendaItem::where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $abertas = (clone $base)->where('status', AgendaItemStatus::ABERTO)->count();
        $emAndamento = (clone $base)->where('status', AgendaItemStatus::EM_ANDAMENTO)->count();
        $concluidas = (clone $base)->where('status', AgendaItemStatus::CONCLUIDO)->count();
        $atrasadas = (clone $base)->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->count();

        $isSqlite = DB::getDriverName() === 'sqlite';
        $avgExpr = $isSqlite
            ? 'AVG(ROUND((julianday(closed_at) - julianday(created_at)) * 24)) as avg_hours'
            : 'AVG(TIMESTAMPDIFF(HOUR, created_at, closed_at)) as avg_hours';

        $tempoMedioConclusao = (clone $base)
            ->where('status', AgendaItemStatus::CONCLUIDO)
            ->whereNotNull('closed_at')
            ->selectRaw($avgExpr)
            ->value('avg_hours');

        return [
            'total' => $total,
            'abertas' => $abertas,
            'em_andamento' => $emAndamento,
            'concluidas' => $concluidas,
            'atrasadas' => $atrasadas,
            'taxa_conclusao' => $total > 0 ? round(($concluidas / $total) * 100, 1) : 0,
            'tempo_medio_horas' => round($tempoMedioConclusao ?? 0, 1),
        ];
    }

    /**
     * Carga de trabalho por responsável.
     */
    public function workload(int $tenantId): array
    {
        $isSqlite = DB::getDriverName() === 'sqlite';
        $nowExpr = $isSqlite ? "datetime('now')" : 'NOW()';

        return AgendaItem::where('tenant_id', $tenantId)
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->whereNotNull('assignee_user_id')
            ->selectRaw('assignee_user_id, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN due_at < {$nowExpr} THEN 1 ELSE 0 END) as atrasadas")
            ->selectRaw('SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END) as urgentes', [AgendaItemPriority::URGENTE->value])
            ->groupBy('assignee_user_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->assignee_user_id,
                'name' => User::find($row->assignee_user_id)?->name ?? 'N/A',
                'total' => $row->total,
                'atrasadas' => $row->atrasadas ?? 0,
                'urgentes' => $row->urgentes ?? 0,
            ])
            ->toArray();
    }

    /**
     * Itens atrasados agrupados por equipe/tipo.
     */
    public function overdueByTeam(int $tenantId): array
    {
        /** @var Collection<int, object{type:string,total:int|string,avg_atraso_horas:float|int|string|null}> $rows */
        $rows = AgendaItem::where('tenant_id', $tenantId)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->selectRaw('type, COUNT(*) as total')
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? "AVG(ROUND((julianday('now') - julianday(due_at)) * 24)) as avg_atraso_horas"
                : 'AVG(TIMESTAMPDIFF(HOUR, due_at, NOW())) as avg_atraso_horas'
            )
            ->groupBy('type')
            ->orderByDesc('total')
            ->get();

        return $rows
            ->map(static fn (object $row) => [
                'type' => $row->type,
                'total' => (int) $row->total,
                'atraso_medio_horas' => round($row->avg_atraso_horas ?? 0, 1),
            ])
            ->toArray();
    }
}
