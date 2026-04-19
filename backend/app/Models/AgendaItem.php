<?php

namespace App\Models;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Models\Concerns\BelongsToTenant;
use App\Services\WebPushService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $type
 * @property string|null $title
 * @property string|null $short_description
 * @property string $status
 * @property string $priority
 * @property string|null $origin
 * @property string|null $visibility
 * @property Carbon|null $due_at
 * @property Carbon|null $remind_at
 * @property Carbon|null $remind_notified_at
 * @property Carbon|null $snooze_until
 * @property Carbon|null $sla_due_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $recurrence_next_at
 * @property int|null $assignee_user_id
 * @property int|null $created_by_user_id
 * @property int|null $closed_by
 * @property string|null $ref_type
 * @property int|null $ref_id
 * @property array|null $context
 * @property array|null $tags
 * @property array|null $visibility_users
 * @property array|null $visibility_departments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $completed
 * @property-read Carbon|null $completedAt
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read User|null $closedByUser
 * @property-read Model|null $source
 * @property-read Collection<int, AgendaItemComment> $comments
 * @property-read Collection<int, AgendaItemHistory> $history
 * @property-read Collection<int, AgendaSubtask> $subtasks
 * @property-read Collection<int, AgendaAttachment> $attachments
 * @property-read Collection<int, AgendaTimeEntry> $timeEntries
 * @property-read Collection<int, AgendaItemWatcher> $watchers
 * @property-read Collection<int, User> $watcherUsers
 * @property-read Collection<int, AgendaItem> $dependsOn
 * @property-read Collection<int, AgendaItem> $blockers
 */
class AgendaItem extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'central_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'remind_notified_at' => 'datetime',
            'snooze_until' => 'datetime',
            'sla_due_at' => 'datetime',
            'closed_at' => 'datetime',
            'context' => 'array',
            'tags' => 'array',
            'recurrence_next_at' => 'datetime',
            'visibility_departments' => 'array',
            'visibility_users' => 'array',
        ];

    }

    public function fill(array $attributes)
    {
        return parent::fill($this->normalizeLegacyAliases($attributes));
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAgendaEnumCase(AgendaItemType::class, $value, [
                'REUNIAO' => AgendaItemType::LEMBRETE->value,
            ]),
            set: fn ($value) => $this->resolveAgendaEnumBackingValue(AgendaItemType::class, $value, [
                'REUNIAO' => AgendaItemType::LEMBRETE->value,
            ]),
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAgendaEnumCase(AgendaItemStatus::class, $value),
            set: fn ($value) => $this->resolveAgendaEnumBackingValue(AgendaItemStatus::class, $value),
        );
    }

    protected function priority(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAgendaEnumCase(AgendaItemPriority::class, $value),
            set: fn ($value) => $this->resolveAgendaEnumBackingValue(AgendaItemPriority::class, $value),
        );
    }

    protected function origin(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAgendaEnumCase(AgendaItemOrigin::class, $value),
            set: fn ($value) => $this->resolveAgendaEnumBackingValue(AgendaItemOrigin::class, $value),
        );
    }

    protected function visibility(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->resolveAgendaEnumCase(AgendaItemVisibility::class, $value),
            set: fn ($value) => $this->resolveAgendaEnumBackingValue(AgendaItemVisibility::class, $value),
        );
    }

    protected function completed(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->attributes['status'] ?? null) === AgendaItemStatus::CONCLUIDO->value,
            set: function (mixed $value): array {
                $isCompleted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $isCompleted ??= (bool) $value;

                $currentStatus = $this->attributes['status'] ?? AgendaItemStatus::ABERTO->value;

                return [
                    'status' => $isCompleted ? AgendaItemStatus::CONCLUIDO->value : $currentStatus,
                    'closed_at' => $isCompleted
                        ? ($this->attributes['closed_at'] ?? now())
                        : null,
                ];
            },
        );
    }

    protected function completedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getAttributeValue('closed_at'),
            set: fn ($value) => ['closed_at' => $value],
        );
    }

    /**
     * Compatibilidade com testes e fluxos legacy que ainda usam user_id/completed.
     */
    public function setUserIdAttribute(?int $value): void
    {
        if ($value === null) {
            return;
        }

        $this->attributes['assignee_user_id'] = $value;
        $this->attributes['created_by_user_id'] ??= $value;
    }

    public function getUserIdAttribute(): ?int
    {
        return isset($this->attributes['assignee_user_id'])
            ? (int) $this->attributes['assignee_user_id']
            : null;
    }

    public function statusEnum(): ?AgendaItemStatus
    {
        return $this->resolveAgendaEnumCase(AgendaItemStatus::class, $this->attributes['status'] ?? null);
    }

    public function priorityEnum(): ?AgendaItemPriority
    {
        return $this->resolveAgendaEnumCase(AgendaItemPriority::class, $this->attributes['priority'] ?? null);
    }

    public function typeEnum(): ?AgendaItemType
    {
        return $this->resolveAgendaEnumCase(AgendaItemType::class, $this->attributes['type'] ?? null, [
            'REUNIAO' => AgendaItemType::LEMBRETE->value,
        ]);
    }

    public function visibilityEnum(): ?AgendaItemVisibility
    {
        return $this->resolveAgendaEnumCase(AgendaItemVisibility::class, $this->attributes['visibility'] ?? null);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->assignee();
    }

    public function criadoPor(): BelongsTo
    {
        return $this->creator();
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AgendaItemComment::class, 'agenda_item_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AgendaItemHistory::class, 'agenda_item_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(AgendaSubtask::class, 'agenda_item_id')->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AgendaAttachment::class, 'agenda_item_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(AgendaTimeEntry::class, 'agenda_item_id');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(AgendaItemWatcher::class, AgendaItemWatcher::itemForeignKey());
    }

    public function watcherUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'central_item_watchers', AgendaItemWatcher::itemForeignKey(), 'user_id')
            ->withPivot(['role', 'notify_status_change', 'notify_comment', 'notify_due_date', 'notify_assignment', 'added_by_type'])
            ->withTimestamps();
    }

    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'central_item_dependencies', 'item_id', 'depends_on_id');
    }

    public function blockers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'central_item_dependencies', 'depends_on_id', 'item_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }

    public function scopeAtrasados(Builder $query): Builder
    {
        return $query->where('status', '!=', AgendaItemStatus::CONCLUIDO)
            ->where('status', '!=', AgendaItemStatus::CANCELADO)
            ->where('due_at', '<', now());
    }

    public function scopeHoje(Builder $query): Builder
    {
        return $query->where('status', '!=', AgendaItemStatus::CONCLUIDO)
            ->where('status', '!=', AgendaItemStatus::CANCELADO)
            ->whereDate('due_at', today())
            ->where(fn ($q) => $q->whereNull('snooze_until')->orWhere('snooze_until', '<=', now()));
    }

    public function scopeSemPrazo(Builder $query): Builder
    {
        return $query->where('status', '!=', AgendaItemStatus::CONCLUIDO)
            ->where('status', '!=', AgendaItemStatus::CANCELADO)
            ->whereNull('due_at');
    }

    public function scopeDoUsuario(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('assignee_user_id', $userId);
    }

    public function scopeDaEquipe(Builder $query, array $userIds): Builder
    {
        return $query->whereIn('assignee_user_id', $userIds)
            ->orWhere('visibility', AgendaItemVisibility::EQUIPE)
            ->orWhere('visibility', AgendaItemVisibility::EMPRESA);
    }

    public function scopeVisivelPara(Builder $query, ?int $userId, ?int $departmentId = null): Builder
    {
        if ($userId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($userId, $departmentId) {
            $q->where('assignee_user_id', $userId)
                ->orWhere('created_by_user_id', $userId)
                ->orWhere('visibility', AgendaItemVisibility::EMPRESA)
                ->orWhere('visibility', AgendaItemVisibility::EQUIPE)
                ->orWhereHas('watchers', fn (Builder $wq) => $wq->where('user_id', $userId));

            if ($departmentId) {
                $q->orWhere(function (Builder $dq) use ($departmentId) {
                    $dq->where('visibility', AgendaItemVisibility::DEPARTAMENTO)
                        ->whereJsonContains('visibility_departments', $departmentId);
                });
            }

            $q->orWhere(function (Builder $cq) use ($userId) {
                $cq->where('visibility', AgendaItemVisibility::CUSTOM)
                    ->whereJsonContains('visibility_users', $userId);
            });
        });
    }

    public static function criarDeOrigem(
        Model $model,
        AgendaItemType $tipo,
        string $title,
        ?int $responsavelId = null,
        array $extras = []
    ): self {
        $tenantId = $model->tenant_id ?? app('current_tenant_id');
        $authUser = auth()->user();
        $authUserId = $authUser instanceof User ? (int) $authUser->id : null;
        $criadoPorUserId = $extras['created_by_user_id']
            ?? $authUserId
            ?? $responsavelId
            ?? User::query()->where('tenant_id', $tenantId)->value('id');

        $responsavelId ??= $criadoPorUserId;

        $payload = self::normalizePayload([
            'tenant_id' => $tenantId,
            'type' => $tipo,
            'origin' => $extras['origin'] ?? $extras['source'] ?? AgendaItemOrigin::AUTO,
            'ref_type' => $model->getMorphClass(),
            'ref_id' => $model->getKey(),
            'title' => $title,
            'short_description' => $extras['short_description'] ?? null,
            'assignee_user_id' => $responsavelId,
            'created_by_user_id' => $criadoPorUserId,
            'status' => $extras['status'] ?? AgendaItemStatus::ABERTO,
            'priority' => $extras['priority'] ?? AgendaItemPriority::MEDIA,
            'visibility' => $extras['visibility'] ?? AgendaItemVisibility::EQUIPE,
            'due_at' => $extras['due_at'] ?? null,
            'remind_at' => $extras['remind_at'] ?? null,
            'snooze_until' => $extras['snooze_until'] ?? null,
            'sla_due_at' => $extras['sla_due_at'] ?? null,
            'closed_at' => $extras['closed_at'] ?? null,
            'closed_by' => $extras['closed_by'] ?? null,
            'context' => $extras['context'] ?? null,
            'tags' => $extras['tags'] ?? null,
        ]);

        // Operação multi-tenant legítima: tenantId vem do $model (registro fonte),
        // scope forTenant centraliza justificativa H2 (re-audit 2026-04-19 data-08).
        $item = static::forTenant($tenantId)->updateOrCreate(
            [
                'ref_type' => $model->getMorphClass(),
                'ref_id' => $model->getKey(),
            ],
            $payload + ['tenant_id' => $tenantId]
        );

        if ($item->wasRecentlyCreated) {
            $item->autoAddOriginWatchers($criadoPorUserId, $responsavelId, $extras['watchers'] ?? []);
            $item->dispararPushSeNecessario($title);
        }

        return $item;
    }

    protected function autoAddOriginWatchers(?int $criadoPorId, ?int $responsavelId, array $extraWatchers = []): void
    {
        $userIds = collect([$criadoPorId, $responsavelId])
            ->merge($extraWatchers)
            ->filter()
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            AgendaItemWatcher::firstOrCreate(
                array_merge(AgendaItemWatcher::itemForeignAttributes($this->id), ['user_id' => (int) $userId]),
                [
                    'role' => 'watcher',
                    'added_by_type' => 'auto',
                    'notify_status_change' => true,
                    'notify_comment' => true,
                    'notify_due_date' => true,
                    'notify_assignment' => true,
                ]
            );
        }
    }

    public function dispararPushSeNecessario(?string $title = null): void
    {
        if (! $this->tenant_id) {
            return;
        }

        $recipients = $this->resolveNotificationRecipients(null, $this->created_by_user_id);
        $body = $this->short_description ?: ($title ?? $this->title);
        $this->enviarPushParaDestinatarios($recipients, "Agenda: {$this->title}", $body, $this->created_by_user_id);
    }

    public static function syncFromSource(Model $source, array $overrides = []): void
    {
        $tenantId = $source->tenant_id ?? app('current_tenant_id');
        if (! $tenantId || ! $source->getKey()) {
            return;
        }

        // Operação multi-tenant legítima: tenantId vem do $source (re-audit data-08).
        $item = static::forTenant($tenantId)
            ->where('ref_type', $source->getMorphClass())
            ->where('ref_id', $source->getKey())
            ->first();

        if (! $item) {
            return;
        }

        $item->fill(self::normalizePayload($overrides));
        $item->save();
    }

    public function gerarNotificacao(
        string $type = 'agenda_item_assigned',
        ?string $title = null,
        ?string $message = null,
        array $extraData = [],
        array $opts = []
    ): void {
        if (! $this->tenant_id) {
            return;
        }

        $notifyEvent = $this->mapTypeToWatcherEvent($type);
        $recipients = $this->resolveNotificationRecipients($notifyEvent, $extraData['actor_user_id'] ?? null);

        $basePayload = array_merge([
            'message' => $message ?? ($this->short_description ?: null),
            'icon' => 'inbox',
            'color' => 'blue',
            'link' => "/central?item={$this->id}",
            'notifiable_type' => self::class,
            'notifiable_id' => $this->id,
            'data' => array_merge([
                'agenda_item_id' => $this->id,
                'status' => $this->statusEnum()?->value,
                'priority' => $this->priorityEnum()?->value,
            ], $extraData),
        ], $opts);

        $pushTitle = $title ?? "Agenda: {$this->title}";
        $pushBody = $message ?? ($this->short_description ?: $this->title);

        foreach ($recipients as $userId) {
            Notification::notify(
                (int) $this->tenant_id,
                (int) $userId,
                $type,
                $pushTitle,
                $basePayload
            );
        }

        $this->enviarPushParaDestinatarios($recipients, $pushTitle, $pushBody, $extraData['actor_user_id'] ?? null);
    }

    protected function enviarPushParaDestinatarios(array $recipientIds, string $title, string $body, ?int $excludeId = null): void
    {
        if (empty($recipientIds) || ! $this->tenant_id) {
            return;
        }

        try {
            $push = app(WebPushService::class);
            $url = "/central?item={$this->id}";
            $typeEnum = $this->typeEnum();
            $itemType = $typeEnum instanceof AgendaItemType
                ? $typeEnum->value
                : (string) ($this->attributes['type'] ?? '');

            foreach ($recipientIds as $userId) {
                if ($excludeId && (int) $userId === (int) $excludeId) {
                    continue;
                }

                $pref = AgendaNotificationPreference::where('user_id', $userId)
                    ->where('tenant_id', $this->tenant_id)
                    ->first();

                if ($pref) {
                    if ($pref->channel_push !== 'on') {
                        continue;
                    }
                    if ($pref->isInQuietHours()) {
                        continue;
                    }
                    if (! $pref->shouldNotifyForType($itemType)) {
                        continue;
                    }
                }

                $push->sendToUser((int) $userId, $title, mb_substr($body, 0, 120), ['url' => $url]);
            }
        } catch (\Throwable $e) {
            Log::warning('Agenda push dispatch failed', ['error' => $e->getMessage()]);
        }
    }

    public function resolveNotificationRecipients(?string $watcherEvent = null, ?int $excludeUserId = null): array
    {
        $userIds = collect();

        if ($this->assignee_user_id) {
            $userIds->push($this->assignee_user_id);
        }
        if ($this->created_by_user_id) {
            $userIds->push($this->created_by_user_id);
        }

        $watcherQuery = $this->watchers();
        if ($watcherEvent) {
            $watcherQuery->where("notify_{$watcherEvent}", true);
        }
        $watcherUserIds = $watcherQuery->pluck('user_id');
        $userIds = $userIds->merge($watcherUserIds);

        if ($excludeUserId) {
            $userIds = $userIds->reject(fn ($id) => (int) $id === (int) $excludeUserId);
        }

        return $userIds->unique()->values()->all();
    }

    public function gerarNotificacaoParaUsuario(
        int $userId,
        string $type = 'agenda_item_assigned',
        ?string $title = null,
        ?string $message = null,
        array $extraData = [],
        array $opts = []
    ): void {
        if (! $this->tenant_id) {
            return;
        }

        Notification::notify(
            (int) $this->tenant_id,
            $userId,
            $type,
            $title ?? "Agenda: {$this->title}",
            array_merge([
                'message' => $message ?? ($this->short_description ?: null),
                'icon' => 'inbox',
                'color' => 'blue',
                'link' => "/central?item={$this->id}",
                'notifiable_type' => self::class,
                'notifiable_id' => $this->id,
                'data' => array_merge([
                    'agenda_item_id' => $this->id,
                    'status' => $this->statusEnum()?->value,
                    'priority' => $this->priorityEnum()?->value,
                ], $extraData),
            ], $opts)
        );
    }

    private function mapTypeToWatcherEvent(string $type): ?string
    {
        return match ($type) {
            'agenda_item_status_changed' => 'status_change',
            'agenda_item_comment' => 'comment',
            'agenda_item_due_soon', 'agenda_item_overdue' => 'due_date',
            'agenda_item_assigned' => 'assignment',
            default => null,
        };
    }

    public function registrarHistorico(
        string $action,
        mixed $from = null,
        mixed $to = null,
        ?int $userId = null
    ): AgendaItemHistory {
        return $this->history()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'from_value' => $this->historyValue($from),
            'to_value' => $this->historyValue($to),
        ]);
    }

    private static function normalizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value instanceof BackedEnum) {
                $payload[$key] = $value->value;
            }
        }

        return $payload;
    }

    private function normalizeLegacyAliases(array $attributes): array
    {
        $ptAliases = [
            'tipo' => 'type',
            'titulo' => 'title',
            'descricao_curta' => 'short_description',
            'prioridade' => 'priority',
            'visibilidade' => 'visibility',
            'contexto' => 'context',
            'ref_tipo' => 'ref_type',
            'responsavel_user_id' => 'assignee_user_id',
            'criado_por_user_id' => 'created_by_user_id',
            'origem' => 'origin',
        ];
        foreach ($ptAliases as $legacy => $canonical) {
            if (array_key_exists($legacy, $attributes) && ! array_key_exists($canonical, $attributes)) {
                $attributes[$canonical] = $attributes[$legacy];
            }
            unset($attributes[$legacy]);
        }

        if (array_key_exists('user_id', $attributes) && ! array_key_exists('assignee_user_id', $attributes)) {
            $attributes['assignee_user_id'] = $attributes['user_id'];
        }

        if (array_key_exists('user_id', $attributes) && ! array_key_exists('created_by_user_id', $attributes)) {
            $attributes['created_by_user_id'] = $attributes['user_id'];
        }

        unset($attributes['user_id']);

        if (array_key_exists('completed_at', $attributes) && ! array_key_exists('closed_at', $attributes)) {
            $attributes['closed_at'] = $attributes['completed_at'];
        }

        unset($attributes['completed_at']);

        if (array_key_exists('completed', $attributes)) {
            $isCompleted = filter_var($attributes['completed'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $isCompleted ??= (bool) $attributes['completed'];

            $attributes['status'] = $isCompleted
                ? AgendaItemStatus::CONCLUIDO->value
                : ($attributes['status'] ?? $this->attributes['status'] ?? AgendaItemStatus::ABERTO->value);

            if ($isCompleted) {
                $attributes['closed_at'] ??= $this->attributes['closed_at'] ?? now();
            } else {
                $attributes['closed_at'] = null;
            }
        }

        unset($attributes['completed']);

        return $attributes;
    }

    private function historyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return Str::limit((string) $value, 255, '');
    }

    private function resolveAgendaEnumCase(string $enumClass, mixed $value, array $legacyAliases = []): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $enumClass) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $lookup = strtoupper($trimmed);
            if (isset($legacyAliases[$lookup])) {
                return $enumClass::tryFrom($legacyAliases[$lookup]);
            }

            $lower = strtolower($trimmed);
            foreach ($enumClass::cases() as $case) {
                if ($case->value === $lower || strtolower($case->name) === $lower || strtoupper($case->name) === $lookup) {
                    return $case;
                }
            }
        }

        return null;
    }

    private function resolveAgendaEnumBackingValue(string $enumClass, mixed $value, array $legacyAliases = []): ?string
    {
        return $this->resolveAgendaEnumCase($enumClass, $value, $legacyAliases)?->value;
    }
}
