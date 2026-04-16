<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property array<int|string, mixed>|null $item_interactions
 * @property int|null $view_count
 * @property int|null $time_spent_seconds
 * @property Carbon|null $first_viewed_at
 * @property Carbon|null $last_viewed_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $expires_at
 */
class CrmInteractiveProposal extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_VIEWED = 'viewed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const RESPONDABLE_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_VIEWED,
    ];

    protected $table = 'crm_interactive_proposals';

    protected $fillable = [
        'tenant_id', 'quote_id', 'deal_id', 'token', 'status',
        'view_count', 'time_spent_seconds', 'item_interactions',
        'client_notes', 'client_signature', 'first_viewed_at',
        'last_viewed_at', 'accepted_at', 'rejected_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'item_interactions' => 'array',
            'view_count' => 'integer',
            'time_spent_seconds' => 'integer',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_SENT => 'Enviada',
        self::STATUS_VIEWED => 'Visualizada',
        self::STATUS_ACCEPTED => 'Aceita',
        self::STATUS_REJECTED => 'Rejeitada',
        self::STATUS_EXPIRED => 'Expirada',
    ];

    // ─── Boot ───────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $proposal) {
            if (empty($proposal->token)) {
                $proposal->token = Str::random(64);
            }
        });
    }

    // ─── Methods ────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canReceiveResponse(): bool
    {
        return in_array($this->status, self::RESPONDABLE_STATUSES, true) && ! $this->isExpired();
    }

    public function recordView(): void
    {
        $this->increment('view_count');
        $this->update([
            'last_viewed_at' => now(),
            'first_viewed_at' => $this->first_viewed_at ?? now(),
            'status' => $this->status === self::STATUS_SENT ? self::STATUS_VIEWED : $this->status,
        ]);
    }

    // ─── Relationships ──────────────────────────────────

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'deal_id');
    }
}
