<?php

namespace App\Models;

use App\Enums\CommissionDisputeStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $resolved_at
 * @property CommissionDisputeStatus|null $status
 */
class CommissionDispute extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'commission_event_id', 'user_id',
        'reason', 'status', 'resolution_notes',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'status' => CommissionDisputeStatus::class,
        ];
    }

    /** @deprecated Use CommissionDisputeStatus::OPEN */
    public const STATUS_OPEN = 'open';

    /** @deprecated Use CommissionDisputeStatus::ACCEPTED */
    public const STATUS_ACCEPTED = 'accepted';

    /** @deprecated Use CommissionDisputeStatus::REJECTED */
    public const STATUS_REJECTED = 'rejected';

    /** @deprecated Use CommissionDisputeStatus::cases() */
    public const STATUSES = [
        self::STATUS_OPEN => ['label' => 'Aberta', 'color' => 'warning'],
        self::STATUS_ACCEPTED => ['label' => 'Aceita', 'color' => 'success'],
        self::STATUS_REJECTED => ['label' => 'Rejeitada', 'color' => 'danger'],
    ];

    public function commissionEvent(): BelongsTo
    {
        return $this->belongsTo(CommissionEvent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === CommissionDisputeStatus::OPEN;
    }
}
