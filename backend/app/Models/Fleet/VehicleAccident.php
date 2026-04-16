<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $occurrence_date
 * @property numeric-string|null $estimated_cost
 * @property array<int|string, mixed>|null $photos
 * @property bool|null $third_party_involved
 */
class VehicleAccident extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'driver_id',
        'occurrence_date',
        'location',
        'description',
        'third_party_involved',
        'third_party_info',
        'police_report_number',
        'photos',
        'estimated_cost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
            'estimated_cost' => 'decimal:2',
            'photos' => 'array',
            'third_party_involved' => 'boolean',
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
