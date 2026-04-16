<?php

namespace App\Models;

use App\Enums\TimeClassificationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\JourneyDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $reference_date
 * @property Carbon|null $operational_approved_at
 * @property Carbon|null $hr_approved_at
 * @property bool|null $is_closed
 * @property int|null $total_minutes_worked
 * @property int|null $total_minutes_overtime
 * @property int|null $total_minutes_travel
 * @property int|null $total_minutes_wait
 * @property int|null $total_minutes_break
 * @property int|null $total_minutes_overnight
 * @property int|null $total_minutes_oncall
 */
class JourneyDay extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JourneyDayFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reference_date',
        'regime_type',
        'total_minutes_worked',
        'total_minutes_overtime',
        'total_minutes_travel',
        'total_minutes_wait',
        'total_minutes_break',
        'total_minutes_overnight',
        'total_minutes_oncall',
        'operational_approval_status',
        'operational_approver_id',
        'operational_approved_at',
        'hr_approval_status',
        'hr_approver_id',
        'hr_approved_at',
        'is_closed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'operational_approved_at' => 'datetime',
            'hr_approved_at' => 'datetime',
            'is_closed' => 'boolean',
            'total_minutes_worked' => 'integer',
            'total_minutes_overtime' => 'integer',
            'total_minutes_travel' => 'integer',
            'total_minutes_wait' => 'integer',
            'total_minutes_break' => 'integer',
            'total_minutes_overnight' => 'integer',
            'total_minutes_oncall' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<JourneyBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(JourneyBlock::class)->orderBy('started_at');
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
