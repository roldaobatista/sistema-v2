<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $notice_date
 * @property Carbon|null $termination_date
 * @property Carbon|null $last_work_day
 * @property numeric-string|null $notice_value
 * @property numeric-string|null $salary_balance_value
 * @property numeric-string|null $vacation_proportional_value
 * @property numeric-string|null $vacation_bonus_value
 * @property numeric-string|null $vacation_overdue_value
 * @property numeric-string|null $vacation_overdue_bonus_value
 * @property numeric-string|null $thirteenth_proportional_value
 * @property numeric-string|null $fgts_balance
 * @property numeric-string|null $fgts_penalty_value
 * @property numeric-string|null $fgts_penalty_rate
 * @property numeric-string|null $other_earnings
 * @property numeric-string|null $other_deductions
 * @property numeric-string|null $inss_deduction
 * @property numeric-string|null $irrf_deduction
 * @property numeric-string|null $total_gross
 * @property numeric-string|null $total_deductions
 * @property numeric-string|null $total_net
 * @property Carbon|null $calculated_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 */
class Rescission extends Model
{
    use BelongsToTenant, HasFactory;

    // ── Type constants ──
    public const TYPE_SEM_JUSTA_CAUSA = 'sem_justa_causa';

    public const TYPE_JUSTA_CAUSA = 'justa_causa';

    public const TYPE_PEDIDO_DEMISSAO = 'pedido_demissao';

    public const TYPE_ACORDO_MUTUO = 'acordo_mutuo';

    public const TYPE_TERMINO_CONTRATO = 'termino_contrato';

    public const TYPES = [
        self::TYPE_SEM_JUSTA_CAUSA,
        self::TYPE_JUSTA_CAUSA,
        self::TYPE_PEDIDO_DEMISSAO,
        self::TYPE_ACORDO_MUTUO,
        self::TYPE_TERMINO_CONTRATO,
    ];

    public const TYPE_LABELS = [
        self::TYPE_SEM_JUSTA_CAUSA => 'Demissão sem Justa Causa',
        self::TYPE_JUSTA_CAUSA => 'Demissão por Justa Causa',
        self::TYPE_PEDIDO_DEMISSAO => 'Pedido de Demissão',
        self::TYPE_ACORDO_MUTUO => 'Acordo Mútuo',
        self::TYPE_TERMINO_CONTRATO => 'Término de Contrato',
    ];

    // ── Notice type constants ──
    public const NOTICE_WORKED = 'worked';

    public const NOTICE_INDEMNIFIED = 'indemnified';

    public const NOTICE_WAIVED = 'waived';

    public const NOTICE_TYPES = [
        self::NOTICE_WORKED,
        self::NOTICE_INDEMNIFIED,
        self::NOTICE_WAIVED,
    ];

    public const NOTICE_TYPE_LABELS = [
        self::NOTICE_WORKED => 'Trabalhado',
        self::NOTICE_INDEMNIFIED => 'Indenizado',
        self::NOTICE_WAIVED => 'Dispensado',
    ];

    // ── Status constants ──
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CALCULATED,
        self::STATUS_APPROVED,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rascunho',
        self::STATUS_CALCULATED => 'Calculada',
        self::STATUS_APPROVED => 'Aprovada',
        self::STATUS_PAID => 'Paga',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'notice_date',
        'termination_date',
        'last_work_day',
        'notice_type',
        'notice_days',
        'notice_value',
        'salary_balance_days',
        'salary_balance_value',
        'vacation_proportional_days',
        'vacation_proportional_value',
        'vacation_bonus_value',
        'vacation_overdue_days',
        'vacation_overdue_value',
        'vacation_overdue_bonus_value',
        'thirteenth_proportional_months',
        'thirteenth_proportional_value',
        'fgts_balance',
        'fgts_penalty_value',
        'fgts_penalty_rate',
        'advance_deductions',
        'hour_bank_payout',
        'other_earnings',
        'other_deductions',
        'inss_deduction',
        'irrf_deduction',
        'total_gross',
        'total_deductions',
        'total_net',
        'status',
        'calculated_by',
        'approved_by',
        'calculated_at',
        'approved_at',
        'paid_at',
        'trct_file_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'notice_date' => 'date',
            'termination_date' => 'date',
            'last_work_day' => 'date',
            'notice_value' => 'decimal:2',
            'salary_balance_value' => 'decimal:2',
            'vacation_proportional_value' => 'decimal:2',
            'vacation_bonus_value' => 'decimal:2',
            'vacation_overdue_value' => 'decimal:2',
            'vacation_overdue_bonus_value' => 'decimal:2',
            'thirteenth_proportional_value' => 'decimal:2',
            'fgts_balance' => 'decimal:2',
            'fgts_penalty_value' => 'decimal:2',
            'fgts_penalty_rate' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'inss_deduction' => 'decimal:2',
            'irrf_deduction' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];

    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Scopes ──

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeCalculated($query)
    {
        return $query->where('status', self::STATUS_CALCULATED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // ── Accessors ──

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getNoticeTypeLabelAttribute(): ?string
    {
        if (! $this->notice_type) {
            return null;
        }

        return self::NOTICE_TYPE_LABELS[$this->notice_type] ?? $this->notice_type;
    }
}
