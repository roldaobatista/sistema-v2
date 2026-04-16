<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $requested_start
 * @property Carbon|null $requested_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 */
class VehiclePoolRequest extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'fleet_vehicle_id',
        'requested_start',
        'requested_end',
        'actual_start',
        'actual_end',
        'purpose',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_start' => 'datetime',
            'requested_end' => 'datetime',
            'actual_start' => 'datetime',
            'actual_end' => 'datetime',
        ];

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }
}
