<?php

namespace App\Services;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemVisibility;
use App\Models\AgendaItem;
use App\Models\AgendaItemComment;
use App\Models\AgendaItemWatcher;
use App\Models\User;
use App\Support\SearchSanitizer;
use BackedEnum;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AgendaService
{
    public function listar(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = AgendaItem::query()
            ->with(['responsavel:id,name', 'criadoPor:id,name', 'source', 'watchers.user:id,name']);

        $user = auth()->user();
        $userId = $user?->id ? (int) $user->id : null;
        $departmentId = $user?->department_id ?? null;

        $scope = $filters['scope'] ?? null;
        $onlyMine = $scope === 'minhas' || ! empty($filters['only_mine']);

        if ($onlyMine && $userId) {
            $query->where('responsavel_user_id', $userId);
        } elseif ($userId) {
            $query->visivelPara($userId, $departmentId);
        }

        if (! empty($filters['search'])) {
            $s = SearchSanitizer::contains($filters['search']);
            $query->where(fn ($q) => $q->where('titulo', 'like', $s)
                ->orWhere('descricao_curta', 'like', $s)
                ->orWhere('ref_id', 'like', $s)
            );
        }

        if (! empty($filters['tipo'])) {
            $query->where('tipo', strtolower((string) $filters['tipo']));
        }

        if (! empty($filters['status'])) {
            $statuses = array_map(fn ($s) => strtolower((string) $s), (array) $filters['status']);
            $query->whereIn('status', $statuses);
        }

        if (! empty($filters['prioridade'])) {
            $query->where('prioridade', strtolower((string) $filters['prioridade']));
        }

        $responsavelId = $filters['responsavel_user_id'] ?? $filters['responsavel'] ?? null;
        if ($responsavelId !== null && $responsavelId !== '') {
            $query->where('responsavel_user_id', (int) $responsavelId);
        }

        if (! empty($filters['criado_por'])) {
            $query->where('criado_por_user_id', (int) $filters['criado_por']);
        }

        if (! empty($filters['visibilidade'])) {
            $query->where('visibilidade', strtolower((string) $filters['visibilidade']));
        }

        $tab = $filters['tab'] ?? $filters['aba'] ?? null;
        if (! empty($tab)) {
            match ($tab) {
                'hoje' => $query->hoje(),
                'atrasadas' => $query->atrasados(),
                'sem_prazo' => $query->semPrazo(),
                'seguindo' => $userId ? $query->whereHas('watchers', fn ($wq) => $wq->where('user_id', $userId)) : null,
                default => null,
            };
        }

        $sort = $filters['sort_by'] ?? null;
        $dir = strtolower((string) ($filters['sort_dir'] ?? 'asc'));
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

        if (empty($sort)) {
            $query->orderByRaw("CASE WHEN prioridade = 'urgent' THEN 1 WHEN prioridade = 'high' THEN 2 WHEN prioridade = 'medium' THEN 3 WHEN prioridade = 'low' THEN 4 ELSE 5 END ASC")
                ->orderBy('due_at', 'asc')
                ->orderBy('created_at', 'desc');
        } elseif ($sort === 'prioridade') {
            $query->orderByRaw("CASE WHEN prioridade = 'urgent' THEN 1 WHEN prioridade = 'high' THEN 2 WHEN prioridade = 'medium' THEN 3 WHEN prioridade = 'low' THEN 4 ELSE 5 END ".($dir === 'desc' ? 'DESC' : 'ASC'))
                ->orderBy('due_at', 'asc')
                ->orderBy('created_at', 'desc');
        } else {
            $allowedSort = in_array($sort, ['due_at', 'created_at', 'titulo', 'prioridade'], true) ? $sort : 'created_at';
            $query->orderBy($allowedSort, $dir);
        }

        return $query->paginate($perPage);
    }

    public function usuarioPodeAcessarItem(AgendaItem $item): bool
    {
        $user = auth()->user();
        $userId = $user?->id;
        if (! $userId) {
            return false;
        }

        if ($item->responsavel_user_id === (int) $userId || $item->criado_por_user_id === (int) $userId) {
            return true;
        }

        $visibility = $item->visibilityEnum();

        if ($visibility === AgendaItemVisibility::EMPRESA || $visibility === AgendaItemVisibility::EQUIPE) {
            return true;
        }

        if ($visibility === AgendaItemVisibility::DEPARTAMENTO) {
            $departmentId = $user->department_id ?? null;
            if ($departmentId && in_array($departmentId, $item->visibility_departments ?? [])) {
                return true;
            }
        }

        if ($visibility === AgendaItemVisibility::CUSTOM) {
            if (in_array($userId, $item->visibility_users ?? [])) {
                return true;
            }
        }

        if ($item->watchers()->where('user_id', $userId)->exists()) {
            return true;
        }

        return false;
    }

    public function criar(array $data): AgendaItem
    {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();
            $data['tenant_id'] = app()->bound('current_tenant_id')
                ? app('current_tenant_id')
                : ($user->current_tenant_id ?? $user->tenant_id);
            $data['criado_por_user_id'] = $user->id;
            $data['responsavel_user_id'] ??= $user->id;

            $data['status'] ??= AgendaItemStatus::ABERTO;
            $data['prioridade'] ??= AgendaItemPriority::MEDIA;
            $data['origem'] ??= AgendaItemOrigin::MANUAL;
            $data['visibilidade'] ??= AgendaItemVisibility::PRIVADO;

            $watcherIds = $data['watchers'] ?? [];
            unset($data['watchers']);

            $data = $this->normalizeItemPayload($data);
            $item = AgendaItem::create($data);

            $this->autoAddWatchers($item, $user->id, $watcherIds);

            $this->logHistory($item, 'created');
            app(AgendaAutomationService::class)->aplicarRegras($item);
            $item->refresh();

            if ($item->responsavel_user_id && $item->responsavel_user_id !== $user->id) {
                $item->gerarNotificacao(
                    'agenda_item_assigned',
                    'Nova tarefa atribuída a você',
                    "{$user->name} atribuiu \"{$item->titulo}\" para você.",
                    ['actor_user_id' => (int) $user->id]
                );
            }

            if (! empty($watcherIds)) {
                $watcherOnlyIds = collect($watcherIds)
                    ->reject(fn ($id) => (int) $id === (int) $user->id || (int) $id === (int) $item->responsavel_user_id)
                    ->values();

                foreach ($watcherOnlyIds as $wid) {
                    $item->gerarNotificacaoParaUsuario(
                        (int) $wid,
                        'agenda_item_watching',
                        'Você foi adicionado como seguidor',
                        "{$user->name} adicionou você como seguidor em \"{$item->titulo}\".",
                        ['actor_user_id' => (int) $user->id]
                    );
                }
            }

            return $item;
        });
    }

    public function atualizar(AgendaItem $item, array $data): AgendaItem
    {
        return DB::transaction(function () use ($item, $data) {
            $oldStatus = $item->statusEnum();
            $oldResponsavel = $item->responsavel_user_id;
            $actorId = (int) (auth()->user()?->id ?? 0);

            $data = $this->normalizeItemPayload($data);

            // Auto-set closed_at/closed_by when completing, and clear them when reopening
            if (isset($data['status'])) {
                $newStatus = $data['status'] instanceof AgendaItemStatus
                    ? $data['status']
                    : AgendaItemStatus::tryFrom((string) $data['status']);

                $isClosing = in_array($newStatus, [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO], true);
                $wasClosed = in_array($oldStatus, [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO], true);

                if ($isClosing && ! $wasClosed) {
                    $data['closed_at'] = $data['closed_at'] ?? now();
                    $data['closed_by'] = $data['closed_by'] ?? $actorId;
                } elseif ($newStatus !== null && ! $isClosing && $wasClosed) {
                    // Reopening: clear closed_at/closed_by
                    $data['closed_at'] = null;
                    $data['closed_by'] = null;
                }
            }

            $item->update($data);

            $newStatus = $item->statusEnum();
            $oldStatusValue = $oldStatus instanceof AgendaItemStatus ? $oldStatus->value : null;
            $newStatusValue = $newStatus instanceof AgendaItemStatus ? $newStatus->value : null;
            if ($newStatusValue !== $oldStatusValue) {
                $this->logHistory($item, 'status_changed', $oldStatusValue, $newStatusValue);
                $item->gerarNotificacao(
                    'agenda_item_status_changed',
                    'Status alterado na Agenda',
                    "O item \"{$item->titulo}\" mudou de ".($oldStatusValue ?? 'indefinido').' para '.($newStatusValue ?? 'indefinido').'.',
                    ['actor_user_id' => $actorId]
                );
            }

            if (isset($data['responsavel_user_id']) && $data['responsavel_user_id'] != $oldResponsavel) {
                $this->logHistory($item, 'assigned', $oldResponsavel, $data['responsavel_user_id']);

                $this->ensureWatcher($item, (int) $data['responsavel_user_id'], 'auto');

                if ((int) $data['responsavel_user_id'] !== $actorId) {
                    $item->gerarNotificacao(
                        'agenda_item_assigned',
                        'Item atribuído a você',
                        "Você foi definido(a) como responsável por \"{$item->titulo}\".",
                        ['actor_user_id' => $actorId]
                    );
                }
            }

            if (isset($data['snooze_until'])) {
                $this->logHistory($item, 'snoozed', null, $data['snooze_until']);
            }

            return $item;
        });
    }

    public function comentar(AgendaItem $item, string $body, int $userId): AgendaItemComment
    {
        $comment = $item->comments()->create([
            'tenant_id' => $item->tenant_id,
            'user_id' => $userId,
            'body' => $body,
        ]);

        $userName = auth()->user()?->name ?? 'Alguém';
        $item->gerarNotificacao(
            'agenda_item_comment',
            'Novo comentário na Agenda',
            "{$userName} comentou em \"{$item->titulo}\": ".mb_substr($body, 0, 80),
            ['actor_user_id' => $userId, 'comment_id' => $comment->id]
        );

        $this->processarMencoes($item, $body, $userId);

        return $comment;
    }

    public function addWatcher(AgendaItem $item, int $userId, string $addedByType = 'manual', ?int $addedByUserId = null): AgendaItemWatcher
    {
        $tenantId = $item->tenant_id;

        return AgendaItemWatcher::firstOrCreate(
            array_merge(AgendaItemWatcher::itemForeignAttributes($item->id), ['user_id' => $userId]),
            [
                'role' => 'watcher',
                'added_by_type' => $addedByType,
                'added_by_user_id' => $addedByUserId ?? auth()->user()?->id,
                'notify_status_change' => true,
                'notify_comment' => true,
                'notify_due_date' => true,
                'notify_assignment' => true,
            ]
        );
    }

    public function removeWatcher(AgendaItem $item, int $watcherId): bool
    {
        return (bool) $item->watchers()->where('id', $watcherId)->delete();
    }

    public function resumo(): array
    {
        $userId = auth()->user()?->id;
        if (! $userId) {
            return ['hoje' => 0, 'atrasadas' => 0, 'sem_prazo' => 0, 'total_aberto' => 0, 'abertas' => 0, 'urgentes' => 0, 'seguindo' => 0];
        }
        $base = AgendaItem::query()->doUsuario($userId);
        $abertas = (clone $base)
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->count();

        $seguindo = AgendaItem::query()
            ->whereHas('watchers', fn ($wq) => $wq->where('user_id', $userId))
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->count();

        return [
            'hoje' => (clone $base)->hoje()->count(),
            'atrasadas' => (clone $base)->atrasados()->count(),
            'sem_prazo' => (clone $base)->semPrazo()->count(),
            'total_aberto' => $abertas,
            'abertas' => $abertas,
            'urgentes' => (clone $base)
                ->where('prioridade', AgendaItemPriority::URGENTE)
                ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
                ->count(),
            'seguindo' => $seguindo,
        ];
    }

    private function autoAddWatchers(AgendaItem $item, int $creatorId, array $extraWatcherIds = []): void
    {
        $this->ensureWatcher($item, $creatorId, 'auto');

        if ($item->responsavel_user_id && $item->responsavel_user_id !== $creatorId) {
            $this->ensureWatcher($item, (int) $item->responsavel_user_id, 'auto');
        }

        foreach ($extraWatcherIds as $watcherId) {
            $watcherId = (int) $watcherId;
            if ($watcherId > 0) {
                $this->ensureWatcher($item, $watcherId, 'manual');
            }
        }
    }

    private function ensureWatcher(AgendaItem $item, int $userId, string $addedByType = 'auto'): void
    {
        AgendaItemWatcher::firstOrCreate(
            array_merge(AgendaItemWatcher::itemForeignAttributes($item->id), ['user_id' => $userId]),
            [
                'role' => 'watcher',
                'added_by_type' => $addedByType,
                'added_by_user_id' => auth()->user()?->id,
                'notify_status_change' => true,
                'notify_comment' => true,
                'notify_due_date' => true,
                'notify_assignment' => true,
            ]
        );
    }

    private function processarMencoes(AgendaItem $item, string $body, int $actorId): void
    {
        if (! preg_match_all('/@(\w+(?:\.\w+)*)/', $body, $matches)) {
            return;
        }

        $names = collect($matches[1])->unique();
        $users = User::where('tenant_id', $item->tenant_id)
            ->where(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhere('name', 'like', SearchSanitizer::contains($name));
                }
            })
            ->pluck('id');

        foreach ($users as $userId) {
            $this->ensureWatcher($item, (int) $userId, 'mention');

            if ((int) $userId !== $actorId) {
                $item->gerarNotificacaoParaUsuario(
                    (int) $userId,
                    'agenda_item_mentioned',
                    'Você foi mencionado(a)',
                    (auth()->user()?->name ?? 'Alguém')." mencionou você em \"{$item->titulo}\".",
                    ['actor_user_id' => $actorId]
                );
            }
        }
    }

    private function logHistory(AgendaItem $item, string $action, ?string $from = null, ?string $to = null): void
    {
        $item->registrarHistorico($action, $from, $to, auth()->user()?->id);
    }

    private function normalizeItemPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value instanceof BackedEnum) {
                $payload[$key] = $value->value;
            }
        }

        foreach (['tipo', 'status', 'prioridade', 'origem', 'visibilidade'] as $enumKey) {
            if (array_key_exists($enumKey, $payload) && $payload[$enumKey] !== null) {
                $payload[$enumKey] = strtolower((string) $payload[$enumKey]);
            }
        }

        return $payload;
    }
}
