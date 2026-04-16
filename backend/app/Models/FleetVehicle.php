<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Fleet\FuelLog;
use App\Models\Fleet\VehicleAccident;
use App\Models\Fleet\VehiclePoolRequest;
use App\Models\Fleet\VehicleTire;
use Database\Factories\FleetVehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $crlv_expiry
 * @property Carbon|null $insurance_expiry
 * @property Carbon|null $next_maintenance
 * @property Carbon|null $tire_change_date
 * @property Carbon|null $cnh_expiry_driver
 * @property numeric-string|null $purchase_value
 * @property numeric-string|null $avg_fuel_consumption
 * @property numeric-string|null $cost_per_km
 * @property int|null $odometer_km
 * @property int|null $year
 */
class FleetVehicle extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FleetVehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'plate', 'brand', 'model', 'year', 'color', 'type', 'fuel_type',
        'odometer_km', 'renavam', 'chassis', 'crlv_expiry', 'insurance_expiry',
        'next_maintenance', 'tire_change_date', 'purchase_value', 'assigned_user_id',
        'status', 'notes', 'avg_fuel_consumption', 'cost_per_km', 'cnh_expiry_driver',
    ];

    protected function casts(): array
    {
        return [
            'crlv_expiry' => 'date',
            'insurance_expiry' => 'date',
            'next_maintenance' => 'date',
            'tire_change_date' => 'date',
            'cnh_expiry_driver' => 'date',
            'purchase_value' => 'decimal:2',
            'avg_fuel_consumption' => 'decimal:2',
            'cost_per_km' => 'decimal:4',
            'odometer_km' => 'integer',
            'year' => 'integer',
        ];

    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(TrafficFine::class);
    }

    public function fuelingLogs(): HasMany
    {
        return $this->hasMany(FuelingLog::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(ToolInventory::class);
    }

    public function tires(): HasMany
    {
        return $this->hasMany(VehicleTire::class, 'fleet_vehicle_id');
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class, 'fleet_vehicle_id');
    }

    public function poolRequests(): HasMany
    {
        return $this->hasMany(VehiclePoolRequest::class, 'fleet_vehicle_id');
    }

    public function accidents(): HasMany
    {
        return $this->hasMany(VehicleAccident::class, 'fleet_vehicle_id');
    }

    public function getAverageConsumptionAttribute(): ?float
    {
        $logs = $this->fuelingLogs()->orderBy('created_at', 'desc')->take(10)->get();
        if ($logs->count() < 2) {
            return null;
        }
        $totalKm = $logs->first()->odometer_km - $logs->last()->odometer_km;
        $totalLiters = $logs->sum('liters');

        return $totalLiters > 0 ? round($totalKm / $totalLiters, 2) : null;
    }
}
