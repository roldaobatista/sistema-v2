<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\HourBankPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $compensation_period_days
 * @property int|null $max_positive_balance_minutes
 * @property int|null $max_negative_balance_minutes
 * @property bool|null $block_on_negative_exceeded
 * @property bool|null $auto_compensate
 * @property bool|null $convert_expired_to_payment
 * @property numeric-string|null $overtime_50_multiplier
 * @property numeric-string|null $overtime_100_multiplier
 * @property array<int|string, mixed>|null $applicable_roles
 * @property array<int|string, mixed>|null $applicable_teams
 * @property array<int|string, mixed>|null $applicable_unions
 * @property bool|null $requires_two_level_approval
 * @property bool|null $is_active
 */
class HourBankPolicy extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<HourBankPolicyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'regime_type',
        'compensation_period_days',
        'max_positive_balance_minutes',
        'max_negative_balance_minutes',
        'block_on_negative_exceeded',
        'auto_compensate',
        'convert_expired_to_payment',
        'overtime_50_multiplier',
        'overtime_100_multiplier',
        'applicable_roles',
        'applicable_teams',
        'applicable_unions',
        'requires_two_level_approval',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'compensation_period_days' => 'integer',
            'max_positive_balance_minutes' => 'integer',
            'max_negative_balance_minutes' => 'integer',
            'block_on_negative_exceeded' => 'boolean',
            'auto_compensate' => 'boolean',
            'convert_expired_to_payment' => 'boolean',
            'overtime_50_multiplier' => 'decimal:2',
            'overtime_100_multiplier' => 'decimal:2',
            'applicable_roles' => 'array',
            'applicable_teams' => 'array',
            'applicable_unions' => 'array',
            'requires_two_level_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isBalanceExceeded(int $currentBalanceMinutes): bool
    {
        if ($currentBalanceMinutes > 0 && $this->max_positive_balance_minutes) {
            return $currentBalanceMinutes > $this->max_positive_balance_minutes;
        }

        if ($currentBalanceMinutes < 0 && $this->max_negative_balance_minutes) {
            return abs($currentBalanceMinutes) > $this->max_negative_balance_minutes;
        }

        return false;
    }

    public function getExpirationDate(Carbon $referenceDate): Carbon
    {
        return $referenceDate->copy()->addDays($this->compensation_period_days);
    }

    public function calculateOvertimeValue(int $minutes, string $type = '50'): float
    {
        $multiplier = $type === '100'
            ? (float) $this->overtime_100_multiplier
            : (float) $this->overtime_50_multiplier;

        return ($minutes / 60) * $multiplier;
    }
}
