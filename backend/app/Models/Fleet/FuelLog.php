<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property int|null $odometer_km
 * @property numeric-string|null $liters
 * @property numeric-string|null $price_per_liter
 * @property numeric-string|null $total_value
 * @property numeric-string|null $consumption_km_l
 */
class FuelLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'driver_id',
        'date',
        'odometer_km',
        'liters',
        'price_per_liter',
        'total_value',
        'fuel_type',
        'gas_station',
        'consumption_km_l',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'odometer_km' => 'integer',
            'liters' => 'decimal:2',
            'price_per_liter' => 'decimal:4',
            'total_value' => 'decimal:2',
            'consumption_km_l' => 'decimal:2',
        ];

    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
