<?php

namespace App\Models;

use App\Enums\AgendaItemStatus;
use App\Enums\ServiceCallStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Traits\SyncsWithAgenda;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string|null $call_number
 * @property int|null $customer_id
 * @property int|null $quote_id
 * @property int|null $contract_id
 * @property int|null $sla_policy_id
 * @property int|null $template_id
 * @property int|null $technician_id
 * @property int|null $driver_id
 * @property int|null $created_by
 * @property ServiceCallStatus $status
 * @property string $priority
 * @property string|null $source
 * @property string|null $source_id
 * @property Carbon|null $scheduled_date
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $sla_due_at
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $google_maps_link
 * @property string|null $observations
 * @property string|null $resolution_notes
 * @property int $reschedule_count
 * @property string|null $reschedule_reason
 * @property array|null $reschedule_history
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool $sla_breached
 * @property-read int|null $response_time_minutes
 * @property-read int|null $resolution_time_minutes
 * @property-read int|null $sla_remaining_minutes
 * @property-read int|null $sla_limit_hours
 * @property-read Customer|null $customer
 * @property-read Quote|null $quote
 * @property-read RecurringContract|null $contract
 * @property-read SlaPolicy|null $slaPolicy
 * @property-read ServiceCallTemplate|null $template
 * @property-read User|null $technician
 * @property-read User|null $driver
 * @property-read User|null $createdBy
 * @property-read Collection<int, Equipment> $equipments
 * @property-read Collection<int, ServiceCallComment> $comments
 * @property-read Collection<int, WorkOrder> $workOrders
 */
class ServiceCall extends Model
{
    use Auditable, BelongsToTenant, \Illuminate\Database\Eloquent\Factories\HasFactory, SoftDeletes, SyncsWithAgenda;

    protected $fillable = [
        'tenant_id', 'call_number', 'customer_id', 'quote_id',
        'contract_id', 'sla_policy_id', 'template_id',
        'technician_id', 'driver_id', 'created_by', 'status', 'priority',
        'source', 'source_id',
        'scheduled_date', 'started_at', 'completed_at', 'sla_due_at',
        'latitude', 'longitude', 'address', 'city', 'state', 'google_maps_link',
        'observations', 'resolution_notes', 'reschedule_count', 'reschedule_reason', 'reschedule_history',
    ];

    protected $appends = [
        'sla_breached',
        'response_time_minutes',
        'resolution_time_minutes',
        'sla_remaining_minutes',
        'sla_limit_hours',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'reschedule_history' => 'array',
            'status' => ServiceCallStatus::class,
        ];
    }

    // ── Backward-compat legacy constants (usados em AlertEngineService etc.)
    /** @deprecated Use ServiceCallStatus::PENDING_SCHEDULING */
    public const STATUS_OPEN = 'pending_scheduling';

    public const STATUS_PENDING_SCHEDULING = 'pending_scheduling';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const STATUS_CONVERTED_TO_OS = 'converted_to_os';

    public const STATUS_CANCELLED = 'cancelled';

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(ServiceCallStatus::cases())
            ->mapWithKeys(fn (ServiceCallStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    public const PRIORITIES = [
        'low' => ['label' => 'Baixa', 'color' => 'text-surface-500'],
        'normal' => ['label' => 'Normal', 'color' => 'text-blue-500'],
        'high' => ['label' => 'Alta', 'color' => 'text-amber-500'],
        'urgent' => ['label' => 'Urgente', 'color' => 'text-red-500'],
    ];

    public function canTransitionTo(string|ServiceCallStatus $newStatus): bool
    {
        $current = $this->status instanceof ServiceCallStatus
            ? $this->status
            : ServiceCallStatus::tryFrom($this->status);

        $target = $newStatus instanceof ServiceCallStatus
            ? $newStatus
            : ServiceCallStatus::tryFrom($newStatus);

        if (! $current || ! $target) {
            return false;
        }

        return $current->canTransitionTo($target);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ServiceCallTemplate::class, 'template_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_call_equipments')
            ->withPivot('observations')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ServiceCallComment::class)->orderByDesc('created_at');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public static function nextNumber(int $tenantId): string
    {
        $cacheKey = "seq_service_call_{$tenantId}";

        $generate = function () use ($tenantId, $cacheKey): string {
            if (! Cache::has($cacheKey)) {
                $last = static::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('id')
                    ->value('call_number');
                $seq = $last ? (int) preg_replace('/\D/', '', $last) : 0;
                Cache::forever($cacheKey, $seq);
            }

            $next = Cache::increment($cacheKey);

            return 'CT-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        };

        try {
            return Cache::lock("lock_{$cacheKey}", 5)->block(5, $generate);
        } catch (\Throwable) {
            // Fallback for cache drivers that don't support locks (e.g., array)
            return $generate();
        }
    }

    // ── SLA ──

    public const SLA_HOURS = [
        'urgent' => 4,
        'high' => 8,
        'normal' => 24,
        'low' => 48,
    ];

    public function getResponseTimeMinutesAttribute(): ?int
    {
        if (! $this->started_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->started_at);
    }

    public function getResolutionTimeMinutesAttribute(): ?int
    {
        if (! $this->completed_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->completed_at);
    }

    public function getSlaBreachedAttribute(): bool
    {
        $priority = $this->priority ?? 'normal';
        $limit = self::SLA_HOURS[$priority] ?? 24;
        if (! $this->created_at) {
            return false;
        }
        $elapsed = $this->completed_at
            ? $this->created_at->diffInHours($this->completed_at)
            : $this->created_at->diffInHours(now());

        return $elapsed > $limit;
    }

    public function getSlaLimitHoursAttribute(): int
    {
        // Dynamic SLA: policy > default by priority
        if ($this->sla_policy_id && $this->relationLoaded('slaPolicy') && $this->slaPolicy) {
            return (int) ceil($this->slaPolicy->resolution_time_minutes / 60);
        }

        return self::SLA_HOURS[$this->priority ?? 'normal'] ?? 24;
    }

    public function getSlaRemainingMinutesAttribute(): ?int
    {
        $statusValue = $this->status instanceof ServiceCallStatus ? $this->status->value : $this->status;
        if (in_array($statusValue, [ServiceCallStatus::CONVERTED_TO_OS->value, ServiceCallStatus::CANCELLED->value], true)) {
            return null;
        }
        if (! $this->created_at) {
            return null;
        }
        $limitMinutes = ($this->sla_limit_hours ?? 24) * 60;
        $elapsedMinutes = (int) $this->created_at->diffInMinutes(now());

        return $limitMinutes - $elapsedMinutes;
    }

    // ── Agenda Sync ──

    public function centralSyncData(): array
    {
        $statusMap = [
            ServiceCallStatus::PENDING_SCHEDULING->value => AgendaItemStatus::ABERTO,
            ServiceCallStatus::SCHEDULED->value => AgendaItemStatus::ABERTO,
            ServiceCallStatus::RESCHEDULED->value => AgendaItemStatus::ABERTO,
            ServiceCallStatus::AWAITING_CONFIRMATION->value => AgendaItemStatus::EM_ANDAMENTO,
            ServiceCallStatus::IN_PROGRESS->value => AgendaItemStatus::EM_ANDAMENTO,
            ServiceCallStatus::CONVERTED_TO_OS->value => AgendaItemStatus::CONCLUIDO,
            ServiceCallStatus::CANCELLED->value => AgendaItemStatus::CANCELADO,
        ];

        $statusValue = $this->status instanceof ServiceCallStatus ? $this->status->value : $this->status;

        return [
            'status' => $statusMap[$statusValue] ?? AgendaItemStatus::ABERTO,
            'title' => "Chamado #{$this->call_number} — {$this->customer?->name}",
        ];
    }
}
