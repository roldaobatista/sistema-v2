<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $used_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $psei_submitted_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $returned_at
 */
class InmetroSeal extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // Status constants
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_USED = 'used';

    public const STATUS_PENDING_PSEI = 'pending_psei';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_DAMAGED = 'damaged';

    public const STATUS_LOST = 'lost';

    // Type constants
    public const TYPE_LACRE = 'seal';

    public const TYPE_SELO_REPARO = 'seal_reparo';

    // PSEI status constants
    public const PSEI_NOT_APPLICABLE = 'not_applicable';

    public const PSEI_PENDING = 'pending';

    public const PSEI_SUBMITTED = 'submitted';

    public const PSEI_CONFIRMED = 'confirmed';

    public const PSEI_FAILED = 'failed';

    // Deadline status constants
    public const DEADLINE_OK = 'ok';

    public const DEADLINE_WARNING = 'warning';

    public const DEADLINE_CRITICAL = 'critical';

    public const DEADLINE_OVERDUE = 'overdue';

    public const DEADLINE_RESOLVED = 'resolved';

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'type',
        'number',
        'status',
        'assigned_to',
        'assigned_at',
        'work_order_id',
        'equipment_id',
        'photo_path',
        'used_at',
        'notes',
        'psei_status',
        'psei_submitted_at',
        'psei_protocol',
        'deadline_at',
        'deadline_status',
        'returned_at',
        'returned_reason',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'assigned_at' => 'datetime',
            'psei_submitted_at' => 'datetime',
            'deadline_at' => 'datetime',
            'returned_at' => 'datetime',
        ];

    }

    // ─── Relationships ──────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RepairSealBatch::class, 'batch_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RepairSealAssignment::class, 'seal_id');
    }

    public function pseiSubmissions(): HasMany
    {
        return $this->hasMany(PseiSubmission::class, 'seal_id');
    }

    public function latestSubmission(): HasOne
    {
        return $this->hasOne(PseiSubmission::class, 'seal_id')->latestOfMany();
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(RepairSealAlert::class, 'seal_id');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopePendingPsei(Builder $query): Builder
    {
        return $query->where('psei_status', self::PSEI_PENDING)
            ->where('type', self::TYPE_SELO_REPARO);
    }

    public function scopeOverdueDeadline(Builder $query): Builder
    {
        return $query->where('deadline_status', self::DEADLINE_OVERDUE);
    }

    public function scopeAssignedToTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->where('assigned_to', $technicianId)
            ->where('status', self::STATUS_ASSIGNED);
    }

    public function scopeByBatch(Builder $query, int $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeUsedWithoutPsei(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SELO_REPARO)
            ->whereIn('status', [self::STATUS_USED, self::STATUS_PENDING_PSEI])
            ->where('psei_status', '!=', self::PSEI_CONFIRMED);
    }

    public function scopeForWorkOrder(Builder $query, int $workOrderId): Builder
    {
        return $query->where('work_order_id', $workOrderId)
            ->whereIn('status', [self::STATUS_USED, self::STATUS_PENDING_PSEI, self::STATUS_REGISTERED]);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getDaysSinceUseAttribute(): ?int
    {
        return $this->used_at ? (int) $this->used_at->diffInDays(now()) : null;
    }

    public function getDeadlineRemainingDaysAttribute(): ?int
    {
        return $this->deadline_at ? (int) now()->diffInDays($this->deadline_at, false) : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast() && $this->psei_status !== self::PSEI_CONFIRMED;
    }

    public function getPseiStatusLabelAttribute(): string
    {
        return match ($this->psei_status) {
            self::PSEI_NOT_APPLICABLE => 'N/A',
            self::PSEI_PENDING => 'Pendente',
            self::PSEI_SUBMITTED => 'Enviado',
            self::PSEI_CONFIRMED => 'Confirmado',
            self::PSEI_FAILED => 'Falhou',
            default => $this->psei_status ?? 'N/A',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_AVAILABLE => 'Disponível',
            self::STATUS_ASSIGNED => 'Com Técnico',
            self::STATUS_USED => 'Usado',
            self::STATUS_PENDING_PSEI => 'Aguardando PSEI',
            self::STATUS_REGISTERED => 'Registrado',
            self::STATUS_RETURNED => 'Devolvido',
            self::STATUS_DAMAGED => 'Danificado',
            self::STATUS_LOST => 'Perdido',
            default => $this->status ?? 'Desconhecido',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_SELO_REPARO ? 'Selo de Reparo' : 'Lacre';
    }

    // ─── Business Logic Helpers ─────────────────────────────

    public function isSealReparo(): bool
    {
        return $this->type === self::TYPE_SELO_REPARO;
    }

    public function isLacre(): bool
    {
        return $this->type === self::TYPE_LACRE;
    }

    public function requiresPsei(): bool
    {
        return $this->isSealReparo();
    }

    public function canBeAssigned(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function canBeUsed(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    public function canBeReturned(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }
}
