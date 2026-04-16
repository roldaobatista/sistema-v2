<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TrafficFineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $fine_date
 * @property Carbon|null $due_date
 * @property numeric-string|null $amount
 * @property int|null $points
 */
class TrafficFine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TrafficFineFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'fleet_vehicle_id', 'driver_id', 'fine_date', 'infraction_code',
        'description', 'amount', 'points', 'status', 'due_date',
    ];

    protected function casts(): array
    {
        return [
            'fine_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'points' => 'integer',
        ];

    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
