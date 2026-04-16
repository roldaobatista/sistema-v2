<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairSealAssignment extends Model
{
    use BelongsToTenant, HasFactory;

    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_RETURNED = 'returned';

    public const ACTION_TRANSFERRED = 'transferred';

    protected $fillable = [
        'tenant_id',
        'seal_id',
        'technician_id',
        'assigned_by',
        'action',
        'previous_technician_id',
        'notes',
    ];

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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function previousTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_technician_id');
    }

    // ─── Scopes ─────────────────────────────────────────────

    public function scopeForTechnician(Builder $query, int $technicianId): Builder
    {
        return $query->where('technician_id', $technicianId);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    // ─── Accessors ──────────────────────────────────────────

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_ASSIGNED => 'Atribuído',
            self::ACTION_RETURNED => 'Devolvido',
            self::ACTION_TRANSFERRED => 'Transferido',
            default => $this->action,
        };
    }
}
