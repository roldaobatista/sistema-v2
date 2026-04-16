<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property numeric-string|null $gross_salary
 * @property numeric-string|null $net_salary
 * @property numeric-string|null $base_salary
 * @property numeric-string|null $overtime_50_hours
 * @property numeric-string|null $overtime_50_value
 * @property numeric-string|null $overtime_100_hours
 * @property numeric-string|null $overtime_100_value
 * @property numeric-string|null $night_hours
 * @property numeric-string|null $night_shift_value
 * @property numeric-string|null $dsr_value
 * @property numeric-string|null $commission_value
 * @property numeric-string|null $bonus_value
 * @property numeric-string|null $other_earnings
 * @property numeric-string|null $inss_employee
 * @property numeric-string|null $irrf
 * @property numeric-string|null $transportation_discount
 * @property numeric-string|null $meal_discount
 * @property numeric-string|null $health_insurance_discount
 * @property numeric-string|null $other_deductions
 * @property numeric-string|null $advance_discount
 * @property numeric-string|null $fgts_value
 * @property numeric-string|null $inss_employer_value
 * @property int|null $worked_days
 * @property int|null $absence_days
 * @property numeric-string|null $absence_value
 * @property int|null $vacation_days
 * @property numeric-string|null $vacation_value
 * @property numeric-string|null $vacation_bonus
 * @property numeric-string|null $thirteenth_value
 * @property int|null $thirteenth_months
 * @property numeric-string|null $hour_bank_payout_hours
 * @property numeric-string|null $hour_bank_payout_value
 * @property numeric-string|null $vt_deduction
 * @property numeric-string|null $vr_deduction
 */
class PayrollLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'payroll_id',
        'user_id',
        'tenant_id',
        'gross_salary',
        'net_salary',
        'base_salary',
        'overtime_50_hours',
        'overtime_50_value',
        'overtime_100_hours',
        'overtime_100_value',
        'night_hours',
        'night_shift_value',
        'dsr_value',
        'commission_value',
        'bonus_value',
        'other_earnings',
        'inss_employee',
        'irrf',
        'transportation_discount',
        'meal_discount',
        'health_insurance_discount',
        'other_deductions',
        'advance_discount',
        'fgts_value',
        'inss_employer_value',
        'worked_days',
        'absence_days',
        'absence_value',
        'vacation_days',
        'vacation_value',
        'vacation_bonus',
        'thirteenth_value',
        'thirteenth_months',
        'hour_bank_payout_hours',
        'hour_bank_payout_value',
        'vt_deduction',
        'vr_deduction',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'base_salary' => 'decimal:2',
            'overtime_50_hours' => 'decimal:2',
            'overtime_50_value' => 'decimal:2',
            'overtime_100_hours' => 'decimal:2',
            'overtime_100_value' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'night_shift_value' => 'decimal:2',
            'dsr_value' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'bonus_value' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'inss_employee' => 'decimal:2',
            'irrf' => 'decimal:2',
            'transportation_discount' => 'decimal:2',
            'meal_discount' => 'decimal:2',
            'health_insurance_discount' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'advance_discount' => 'decimal:2',
            'fgts_value' => 'decimal:2',
            'inss_employer_value' => 'decimal:2',
            'worked_days' => 'integer',
            'absence_days' => 'integer',
            'absence_value' => 'decimal:2',
            'vacation_days' => 'integer',
            'vacation_value' => 'decimal:2',
            'vacation_bonus' => 'decimal:2',
            'thirteenth_value' => 'decimal:2',
            'thirteenth_months' => 'integer',
            'hour_bank_payout_hours' => 'decimal:2',
            'hour_bank_payout_value' => 'decimal:2',
            'vt_deduction' => 'decimal:2',
            'vr_deduction' => 'decimal:2',
        ];

    }

    // ── Relationships ──

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    // ── Accessors ──

    public function getTotalEarningsAttribute(): float
    {
        return round(
            (float) $this->base_salary
            + (float) $this->overtime_50_value
            + (float) $this->overtime_100_value
            + (float) $this->night_shift_value
            + (float) $this->dsr_value
            + (float) $this->commission_value
            + (float) $this->bonus_value
            + (float) $this->other_earnings
            + (float) $this->vacation_value
            + (float) $this->vacation_bonus
            + (float) $this->thirteenth_value,
            2
        );
    }

    public function getTotalDeductionsAttribute(): float
    {
        return round(
            (float) $this->inss_employee
            + (float) $this->irrf
            + (float) $this->transportation_discount
            + (float) $this->meal_discount
            + (float) $this->health_insurance_discount
            + (float) $this->other_deductions
            + (float) $this->advance_discount
            + (float) $this->absence_value,
            2
        );
    }
}
