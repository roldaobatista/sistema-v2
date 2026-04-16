<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string|null $daily_hours
 * @property numeric-string|null $weekly_hours
 * @property int|null $overtime_weekday_pct
 * @property int|null $overtime_weekend_pct
 * @property int|null $overtime_holiday_pct
 * @property int|null $night_shift_pct
 * @property int|null $hour_bank_expiry_months
 * @property bool|null $uses_hour_bank
 * @property bool|null $is_default
 * @property bool|null $allow_negative_hour_bank_deduction
 * @property int|null $daily_hours_limit
 * @property int|null $weekly_hours_limit
 * @property int|null $monthly_hours_limit
 * @property int|null $break_minutes
 * @property bool|null $displacement_counts_as_work
 * @property bool|null $wait_time_counts_as_work
 * @property bool|null $travel_meal_counts_as_break
 * @property bool|null $auto_suggest_clock_on_displacement
 * @property bool|null $pre_assigned_break
 * @property int|null $overnight_min_hours
 * @property int|null $oncall_multiplier_percent
 * @property int|null $overtime_50_percent_limit
 * @property int|null $overtime_100_percent_limit
 * @property bool|null $saturday_is_overtime
 * @property bool|null $sunday_is_overtime
 * @property array<int|string, mixed>|null $custom_rules
 * @property bool|null $is_active
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
 */
class JourneyRule extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // --- Campos legados (Wave 1 — horas decimais, percentuais CLT) ---
    // --- Campos novos (Motor Operacional — minutos, políticas expandidas) ---
    // --- Campos banco de horas expandido (absorvido de HourBankPolicy) ---
    protected $fillable = [
        'tenant_id', 'name', 'daily_hours', 'weekly_hours',
        'overtime_weekday_pct', 'overtime_weekend_pct', 'overtime_holiday_pct',
        'night_shift_pct', 'night_start', 'night_end',
        'uses_hour_bank', 'hour_bank_expiry_months', 'agreement_type', 'is_default',
        'allow_negative_hour_bank_deduction',
        // Motor Operacional (absorvido de JourneyPolicy)
        'daily_hours_limit', 'weekly_hours_limit', 'monthly_hours_limit', 'break_minutes',
        'displacement_counts_as_work', 'wait_time_counts_as_work', 'travel_meal_counts_as_break',
        'auto_suggest_clock_on_displacement', 'pre_assigned_break',
        'overnight_min_hours', 'oncall_multiplier_percent',
        'overtime_50_percent_limit', 'overtime_100_percent_limit',
        'saturday_is_overtime', 'sunday_is_overtime', 'custom_rules',
        'regime_type', 'is_active',
        // Banco de horas expandido (absorvido de HourBankPolicy)
        'compensation_period_days', 'max_positive_balance_minutes', 'max_negative_balance_minutes',
        'block_on_negative_exceeded', 'auto_compensate', 'convert_expired_to_payment',
        'overtime_50_multiplier', 'overtime_100_multiplier',
        'applicable_roles', 'applicable_teams', 'applicable_unions',
        'requires_two_level_approval',
    ];

    protected function casts(): array
    {
        return [
            // Legado
            'daily_hours' => 'decimal:2',
            'weekly_hours' => 'decimal:2',
            'overtime_weekday_pct' => 'integer',
            'overtime_weekend_pct' => 'integer',
            'overtime_holiday_pct' => 'integer',
            'night_shift_pct' => 'integer',
            'hour_bank_expiry_months' => 'integer',
            'uses_hour_bank' => 'boolean',
            'is_default' => 'boolean',
            'allow_negative_hour_bank_deduction' => 'boolean',
            // Motor Operacional
            'daily_hours_limit' => 'integer',
            'weekly_hours_limit' => 'integer',
            'monthly_hours_limit' => 'integer',
            'break_minutes' => 'integer',
            'displacement_counts_as_work' => 'boolean',
            'wait_time_counts_as_work' => 'boolean',
            'travel_meal_counts_as_break' => 'boolean',
            'auto_suggest_clock_on_displacement' => 'boolean',
            'pre_assigned_break' => 'boolean',
            'overnight_min_hours' => 'integer',
            'oncall_multiplier_percent' => 'integer',
            'overtime_50_percent_limit' => 'integer',
            'overtime_100_percent_limit' => 'integer',
            'saturday_is_overtime' => 'boolean',
            'sunday_is_overtime' => 'boolean',
            'custom_rules' => 'array',
            'is_active' => 'boolean',
            // Banco de horas expandido
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
        ];
    }

    // === Scopes (legado) ===

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // === Scopes (Motor Operacional) ===

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // === Methods (Motor Operacional — absorvido de JourneyPolicy) ===

    public function isOvertimeDay(Carbon $date): bool
    {
        if ($date->isSaturday() && $this->saturday_is_overtime) {
            return true;
        }

        if ($date->isSunday() && $this->sunday_is_overtime) {
            return true;
        }

        return false;
    }

    // === Methods (Banco de horas — absorvido de HourBankPolicy) ===

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
        return $referenceDate->copy()->addDays($this->compensation_period_days ?? 30);
    }

    public function calculateOvertimeValue(int $minutes, string $type = '50'): float
    {
        $multiplier = $type === '100'
            ? (float) ($this->overtime_100_multiplier ?? 2.00)
            : (float) ($this->overtime_50_multiplier ?? 1.50);

        return ($minutes / 60) * $multiplier;
    }
}
