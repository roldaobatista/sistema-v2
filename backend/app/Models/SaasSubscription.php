<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\SetsCreatedBy;
use App\Support\Decimal;
use Database\Factories\SaasSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $price
 * @property numeric-string|null $discount
 * @property Carbon|null $started_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancelled_at
 */
class SaasSubscription extends Model
{
    use Auditable, BelongsToTenant;

    /** @use HasFactory<SaasSubscriptionFactory> */
    use HasFactory;

    use SetsCreatedBy;

    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_ANNUAL = 'annual';

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'billing_cycle', 'price', 'discount',
        'started_at', 'trial_ends_at', 'current_period_start', 'current_period_end',
        'cancelled_at', 'cancellation_reason', 'payment_gateway',
        'gateway_subscription_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'started_at' => 'date',
            'trial_ends_at' => 'date',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'cancelled_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SaasPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIAL]);
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function isExpired(): bool
    {
        return $this->current_period_end->isPast() && $this->status !== self::STATUS_ACTIVE;
    }

    public function getEffectivePriceAttribute(): string
    {
        return bcsub(Decimal::string($this->price), Decimal::string($this->discount), 2);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function renew(): void
    {
        $start = $this->current_period_end;
        $end = $this->billing_cycle === self::CYCLE_ANNUAL
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);
    }
}
