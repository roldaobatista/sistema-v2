<?php

namespace App\Models;

use App\Enums\TimeClassificationType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property numeric-string|null $scheduled_hours
 * @property numeric-string|null $worked_hours
 * @property numeric-string|null $overtime_hours_50
 * @property numeric-string|null $overtime_hours_100
 * @property numeric-string|null $night_hours
 * @property numeric-string|null $absence_hours
 * @property numeric-string|null $hour_bank_balance
 * @property bool|null $overtime_limit_exceeded
 * @property bool|null $tolerance_applied
 * @property numeric-string|null $inter_shift_hours
 * @property bool|null $is_holiday
 * @property bool|null $is_dsr
 * @property int|null $total_minutes_worked
 * @property int|null $total_minutes_overtime
 * @property int|null $total_minutes_travel
 * @property int|null $total_minutes_wait
 * @property int|null $total_minutes_break
 * @property int|null $total_minutes_overnight
 * @property int|null $total_minutes_oncall
 * @property Carbon|null $operational_approved_at
 * @property Carbon|null $hr_approved_at
 * @property bool|null $is_closed
 */
class JourneyEntry extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // --- Campos legados (Wave 1 — horas decimais, cálculo CLT) ---
    // --- Campos novos (Motor Operacional — minutos, aprovação dual) ---
    protected $fillable = [
        'tenant_id', 'user_id', 'date', 'journey_rule_id',
        'scheduled_hours', 'worked_hours',
        'overtime_hours_50', 'overtime_hours_100',
        'night_hours', 'absence_hours', 'hour_bank_balance',
        'overtime_limit_exceeded', 'tolerance_applied',
        'break_compliance', 'inter_shift_hours',
        'is_holiday', 'is_dsr', 'status', 'notes',
        // Motor Operacional
        'total_minutes_worked', 'total_minutes_overtime', 'total_minutes_travel',
        'total_minutes_wait', 'total_minutes_break', 'total_minutes_overnight',
        'total_minutes_oncall',
        'operational_approval_status', 'operational_approver_id', 'operational_approved_at',
        'hr_approval_status', 'hr_approver_id', 'hr_approved_at',
        'is_closed', 'regime_type',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'scheduled_hours' => 'decimal:2',
            'worked_hours' => 'decimal:2',
            'overtime_hours_50' => 'decimal:2',
            'overtime_hours_100' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'absence_hours' => 'decimal:2',
            'hour_bank_balance' => 'decimal:2',
            'overtime_limit_exceeded' => 'boolean',
            'tolerance_applied' => 'boolean',
            'inter_shift_hours' => 'decimal:2',
            'is_holiday' => 'boolean',
            'is_dsr' => 'boolean',
            // Motor Operacional
            'total_minutes_worked' => 'integer',
            'total_minutes_overtime' => 'integer',
            'total_minutes_travel' => 'integer',
            'total_minutes_wait' => 'integer',
            'total_minutes_break' => 'integer',
            'total_minutes_overnight' => 'integer',
            'total_minutes_oncall' => 'integer',
            'operational_approved_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'is_closed' => 'boolean',
        ];
    }

    // === Relationships (legado) ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journeyRule(): BelongsTo
    {
        return $this->belongsTo(JourneyRule::class);
    }

    // === Relationships (Motor Operacional) ===

    /**
     * @return HasMany<JourneyBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(JourneyBlock::class, 'journey_entry_id')->orderBy('started_at');
    }

    /**
     * @return HasMany<JourneyApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(JourneyApproval::class, 'journey_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operationalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operational_approver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approver_id');
    }

    // === Scopes (legado) ===

    public function scopeForMonth($query, int $userId, string $yearMonth)
    {
        [$year, $month] = explode('-', $yearMonth);

        return $query->where('user_id', $userId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    // === Accessors (legado) ===

    public function getTotalOvertimeAttribute(): float
    {
        return bcadd($this->overtime_hours_50, $this->overtime_hours_100, 2);
    }

    // === Methods (Motor Operacional) ===

    public function recalculateTotals(): void
    {
        $blocks = $this->blocks()->whereNotNull('duration_minutes')->get();

        $this->update([
            'total_minutes_worked' => $blocks->filter(fn ($b) => in_array($b->classification, [
                TimeClassificationType::JORNADA_NORMAL,
                TimeClassificationType::EXECUCAO_SERVICO,
            ]))->sum('duration_minutes'),
            'total_minutes_overtime' => $blocks->where('classification', TimeClassificationType::HORA_EXTRA)->sum('duration_minutes'),
            'total_minutes_travel' => $blocks->filter(fn ($b) => in_array($b->classification, [
                TimeClassificationType::DESLOCAMENTO_CLIENTE,
                TimeClassificationType::DESLOCAMENTO_ENTRE,
            ]))->sum('duration_minutes'),
            'total_minutes_wait' => $blocks->where('classification', TimeClassificationType::ESPERA_LOCAL)->sum('duration_minutes'),
            'total_minutes_break' => $blocks->filter(fn ($b) => in_array($b->classification, [
                TimeClassificationType::INTERVALO,
                TimeClassificationType::ALMOCO_VIAGEM,
            ]))->sum('duration_minutes'),
            'total_minutes_overnight' => $blocks->where('classification', TimeClassificationType::PERNOITE)->sum('duration_minutes'),
            'total_minutes_oncall' => $blocks->filter(fn ($b) => in_array($b->classification, [
                TimeClassificationType::SOBREAVISO,
                TimeClassificationType::PLANTAO,
            ]))->sum('duration_minutes'),
        ]);

        // Sync minutos → horas decimais para compatibilidade legada
        $this->update([
            'worked_hours' => round($this->total_minutes_worked / 60, 2),
            'overtime_hours_50' => round($this->total_minutes_overtime / 60, 2),
        ]);
    }

    public function isPendingApproval(): bool
    {
        return $this->operational_approval_status === 'pending'
            || $this->hr_approval_status === 'pending';
    }

    public function isFullyApproved(): bool
    {
        return $this->operational_approval_status === 'approved'
            && $this->hr_approval_status === 'approved';
    }
}
