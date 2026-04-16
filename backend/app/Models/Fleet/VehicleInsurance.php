<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FleetVehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $premium_value
 * @property numeric-string|null $deductible_value
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
class VehicleInsurance extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'vehicle_insurances';

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'insurer',
        'policy_number',
        'coverage_type',
        'premium_value',
        'deductible_value',
        'start_date',
        'end_date',
        'broker_name',
        'broker_phone',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'premium_value' => 'decimal:2',
            'deductible_value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];

    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return ! $this->isExpired() && $this->end_date->diffInDays(now()) <= $days;
    }
}
