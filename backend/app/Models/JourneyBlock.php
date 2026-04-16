<?php

namespace App\Models;

use App\Enums\TimeClassificationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\JourneyBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property TimeClassificationType|null $classification
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_minutes
 * @property array<int|string, mixed>|null $metadata
 * @property bool|null $is_auto_classified
 * @property bool|null $is_manually_adjusted
 */
class JourneyBlock extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JourneyBlockFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'journey_day_id',
        'journey_entry_id',
        'user_id',
        'classification',
        'started_at',
        'ended_at',
        'duration_minutes',
        'work_order_id',
        'time_clock_entry_id',
        'fleet_trip_id',
        'schedule_id',
        'metadata',
        'source',
        'is_auto_classified',
        'is_manually_adjusted',
        'adjusted_by',
        'adjustment_reason',
    ];

    protected function casts(): array
    {
        return [
            'classification' => TimeClassificationType::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_minutes' => 'integer',
            'metadata' => 'array',
            'is_auto_classified' => 'boolean',
            'is_manually_adjusted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<JourneyDay, $this>
     */
    public function journeyDay(): BelongsTo
    {
        return $this->belongsTo(JourneyDay::class);
    }

    /**
     * @return BelongsTo<JourneyEntry, $this>
     */
    public function journeyEntry(): BelongsTo
    {
        return $this->belongsTo(JourneyEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<TimeClockEntry, $this>
     */
    public function timeClockEntry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class);
    }

    /**
     * @return BelongsTo<FleetTrip, $this>
     */
    public function fleetTrip(): BelongsTo
    {
        return $this->belongsTo(FleetTrip::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function calculateDuration(): int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return 0;
        }

        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public function isWorkTime(): bool
    {
        return $this->classification->isWorkTime();
    }

    public function isPaidTime(): bool
    {
        return $this->classification->isPaidTime();
    }
}
