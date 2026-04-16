<?php

namespace App\Models;

use App\Enums\CommissionEventStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string|null $base_amount
 * @property numeric-string|null $commission_amount
 * @property numeric-string|null $proportion
 * @property CommissionEventStatus|null $status
 */
class CommissionEvent extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'commission_rule_id', 'work_order_id', 'account_receivable_id', 'user_id',
        'settlement_id', 'base_amount', 'commission_amount', 'proportion', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'proportion' => 'decimal:4',
            'status' => CommissionEventStatus::class,
        ];
    }

    // ── Status (via Enum — constantes mantidas para backward compat) ──
    /** @deprecated Use CommissionEventStatus::PENDING */
    public const STATUS_PENDING = 'pending';

    /** @deprecated Use CommissionEventStatus::APPROVED */
    public const STATUS_APPROVED = 'approved';

    /** @deprecated Use CommissionEventStatus::PAID */
    public const STATUS_PAID = 'paid';

    /** @deprecated Use CommissionEventStatus::REVERSED */
    public const STATUS_REVERSED = 'reversed';

    /** @deprecated Use CommissionEventStatus::CANCELLED */
    public const STATUS_CANCELLED = 'cancelled';

    /** @return array<string, array{label: string, color: string}> */
    public static function statuses(): array
    {
        return collect(CommissionEventStatus::cases())
            ->mapWithKeys(fn (CommissionEventStatus $s) => [$s->value => ['label' => $s->label(), 'color' => $s->color()]])
            ->all();
    }

    /** @deprecated Use CommissionEventStatus::cases() or self::statuses() */
    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'Pendente', 'color' => 'warning'],
        self::STATUS_APPROVED => ['label' => 'Aprovado', 'color' => 'info'],
        self::STATUS_PAID => ['label' => 'Pago', 'color' => 'success'],
        self::STATUS_REVERSED => ['label' => 'Estornado', 'color' => 'danger'],
        self::STATUS_CANCELLED => ['label' => 'Cancelado', 'color' => 'danger'],
    ];

    public const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_PAID, self::STATUS_REVERSED, self::STATUS_PENDING],
        self::STATUS_PAID => [self::STATUS_REVERSED],
        self::STATUS_REVERSED => [],
        self::STATUS_CANCELLED => [],
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class);
    }
}
