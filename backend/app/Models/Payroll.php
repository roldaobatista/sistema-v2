<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $total_gross
 * @property numeric-string|null $total_deductions
 * @property numeric-string|null $total_net
 * @property numeric-string|null $total_fgts
 * @property numeric-string|null $total_inss_employer
 * @property int|null $employee_count
 * @property Carbon|null $calculated_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 */
class Payroll extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'reference_month',
        'type',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'total_fgts',
        'total_inss_employer',
        'employee_count',
        'calculated_by',
        'approved_by',
        'calculated_at',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_fgts' => 'decimal:2',
            'total_inss_employer' => 'decimal:2',
            'employee_count' => 'integer',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];

    }

    // ── Relationships ──

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hourBankPayouts(): HasMany
    {
        return $this->hasMany(HourBankTransaction::class, 'payout_payroll_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ── Scopes ──

    public function scopeForMonth($query, string $month)
    {
        return $query->where('reference_month', $month);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCalculated($query)
    {
        return $query->where('status', 'calculated');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
