<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property numeric-string|null $distance_km
 * @property int|null $odometer_start
 * @property int|null $odometer_end
 */
class FleetTrip extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'fleet_id', 'driver_user_id', 'date', 'origin', 'destination',
        'distance_km', 'purpose', 'odometer_start', 'odometer_end', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'distance_km' => 'decimal:2',
            'odometer_start' => 'integer',
            'odometer_end' => 'integer',
        ];
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }
}
