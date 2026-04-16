<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $departure_date
 * @property Carbon|null $return_date
 * @property int|null $estimated_days
 * @property numeric-string|null $daily_allowance_amount
 * @property numeric-string|null $total_advance_requested
 * @property bool|null $requires_vehicle
 * @property bool|null $requires_overnight
 * @property int|null $rest_days_after
 * @property bool|null $overtime_authorized
 * @property array<int|string, mixed>|null $work_orders
 * @property array<int|string, mixed>|null $itinerary
 * @property array<int|string, mixed>|null $meal_policy
 */
class TravelRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TravelRequestFactory> */
    use HasFactory;

    use SoftDeletes;

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'user_id', 'approved_by', 'status',
        'destination', 'purpose', 'departure_date', 'return_date',
        'departure_time', 'return_time', 'estimated_days',
        'daily_allowance_amount', 'total_advance_requested',
        'requires_vehicle', 'fleet_vehicle_id', 'requires_overnight',
        'rest_days_after', 'overtime_authorized',
        'work_orders', 'itinerary', 'meal_policy',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'estimated_days' => 'integer',
            'daily_allowance_amount' => 'decimal:2',
            'total_advance_requested' => 'decimal:2',
            'requires_vehicle' => 'boolean',
            'requires_overnight' => 'boolean',
            'rest_days_after' => 'integer',
            'overtime_authorized' => 'boolean',
            'work_orders' => 'array',
            'itinerary' => 'array',
            'meal_policy' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<FleetVehicle, $this>
     */
    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    /**
     * @return HasMany<OvernightStay, $this>
     */
    public function overnightStays(): HasMany
    {
        return $this->hasMany(OvernightStay::class);
    }

    /**
     * @return HasMany<TravelAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(TravelAdvance::class);
    }

    /**
     * @return HasOne<TravelExpenseReport, $this>
     */
    public function expenseReport(): HasOne
    {
        return $this->hasOne(TravelExpenseReport::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function totalAdvancesPaid(): float
    {
        return (float) $this->advances()->where('status', 'paid')->sum('amount');
    }
}
