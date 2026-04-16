<?php

namespace App\Models;

use App\Enums\CommissionEventStatus;
use App\Enums\CommissionSettlementStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property numeric-string|null $total_amount
 * @property numeric-string|null $paid_amount
 * @property Carbon|null $paid_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $approved_at
 * @property CommissionSettlementStatus|null $status
 */
class CommissionSettlement extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'period', 'total_amount', 'events_count', 'status',
        'closed_by', 'closed_at', 'approved_by', 'approved_at', 'rejection_reason',
        'paid_at', 'paid_amount', 'payment_notes',
    ];

    protected $appends = ['balance'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'date',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
            'status' => CommissionSettlementStatus::class,
        ];
    }

    public function getBalanceAttribute(): string
    {
        $total = (string) ($this->total_amount ?? 0);
        $paid = (string) ($this->paid_amount ?? 0);

        return bcsub($total, $paid, 2);
    }

    // ── Status (via Enum — constantes mantidas para backward compat) ──
    /** @deprecated Use CommissionSettlementStatus::OPEN */
    public const STATUS_OPEN = 'open';

    /** @deprecated Use CommissionSettlementStatus::CLOSED */
    public const STATUS_CLOSED = 'closed';

    /** @deprecated Use CommissionSettlementStatus::PENDING_APPROVAL */
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    /** @deprecated Use CommissionSettlementStatus::APPROVED */
    public const STATUS_APPROVED = 'approved';

    /** @deprecated Use CommissionSettlementStatus::REJECTED */
    public const STATUS_REJECTED = 'rejected';

    /** @deprecated Use CommissionSettlementStatus::PAID */
    public const STATUS_PAID = 'paid';

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(CommissionSettlementStatus::cases())
            ->mapWithKeys(fn (CommissionSettlementStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    /** @deprecated Use CommissionSettlementStatus::cases() or self::statuses() */
    public const STATUSES = [
        self::STATUS_OPEN => ['label' => 'Aberto', 'color' => 'warning'],
        self::STATUS_CLOSED => ['label' => 'Fechado', 'color' => 'info'],
        self::STATUS_PENDING_APPROVAL => ['label' => 'Aguard. Aprovação', 'color' => 'amber'],
        self::STATUS_APPROVED => ['label' => 'Aprovado', 'color' => 'success'],
        self::STATUS_REJECTED => ['label' => 'Rejeitado', 'color' => 'danger'],
        self::STATUS_PAID => ['label' => 'Pago', 'color' => 'success'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CommissionEvent::class, 'settlement_id');
    }

    /**
     * Eventos do período (fallback para quando settlement_id ainda não está preenchido).
     */
    public function eventsByPeriod(): HasMany
    {
        return $this->hasMany(CommissionEvent::class, 'user_id', 'user_id')
            ->where('tenant_id', $this->tenant_id)
            ->when($this->period, function ($q) {
                $driver = DB::getDriverName();
                if ($driver === 'sqlite') {
                    $q->whereRaw("strftime('%Y-%m', created_at) = ?", [$this->period]);
                } else {
                    $q->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$this->period]);
                }
            });
    }

    /**
     * Recalcular total_amount e events_count a partir dos eventos vinculados.
     */
    public function recalculateTotals(): self
    {
        $events = CommissionEvent::where('tenant_id', $this->tenant_id)
            ->where('settlement_id', $this->id)
            ->whereIn('status', [CommissionEventStatus::APPROVED, CommissionEventStatus::PAID])
            ->get();

        $this->update([
            'total_amount' => $events->reduce(
                fn (string $carry, $e) => bcadd($carry, (string) ($e->commission_amount ?? 0), 2),
                '0'
            ),
            'events_count' => $events->count(),
        ]);

        return $this;
    }
}
