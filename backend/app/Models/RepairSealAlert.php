<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 */
class RepairSealAlert extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_WARNING_3D = 'warning_3d';

    public const TYPE_CRITICAL_4D = 'critical_4d';

    public const TYPE_OVERDUE_5D = 'overdue_5d';

    public const TYPE_BLOCKED = 'blocked';

    public const TYPE_LOW_STOCK = 'low_stock';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'tenant_id',
        'seal_id',
        'technician_id',
        'work_order_id',
        'alert_type',
        'severity',
        'message',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];

    }

    // ─── Relationships ──────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function seal(): BelongsTo
    {
        return $this->belongsTo(InmetroSeal::class, 'seal_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeForTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->where('technician_id', $technicianId);
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('alert_type', $type);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getIsResolvedAttribute(): bool
    {
        return $this->resolved_at !== null;
    }

    public function getAlertTypeLabelAttribute(): string
    {
        return match ($this->alert_type) {
            self::TYPE_WARNING_3D => 'Aviso 3 dias',
            self::TYPE_CRITICAL_4D => 'Crítico 4 dias',
            self::TYPE_OVERDUE_5D => 'Vencido 5+ dias',
            self::TYPE_BLOCKED => 'Técnico Bloqueado',
            self::TYPE_LOW_STOCK => 'Estoque Baixo',
            default => $this->alert_type,
        };
    }

    // ─── Methods ────────────────────────────────────────────

    public function acknowledge(int $userId): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public function resolve(int $userId): void
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }
}
