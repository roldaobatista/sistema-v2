<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToTenant;
use App\Models\FleetVehicle;
use Database\Factories\Fleet\VehicleTireFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $tread_depth
 * @property int|null $retread_count
 * @property Carbon|null $installed_at
 * @property int|null $installed_km
 */
class VehicleTire extends Model
{
    /** @use HasFactory<VehicleTireFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'serial_number',
        'brand',
        'model',
        'position',
        'tread_depth',
        'retread_count',
        'installed_at',
        'installed_km',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tread_depth' => 'decimal:2',
            'retread_count' => 'integer',
            'installed_at' => 'date',
            'installed_km' => 'integer',
        ];

    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'fleet_vehicle_id');
    }
}
