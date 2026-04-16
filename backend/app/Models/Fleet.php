<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fleet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'plate', 'brand', 'model', 'year', 'color', 'type', 'status', 'mileage', 'is_active',
    ];

    public function fuelEntries()
    {
        return $this->hasMany(FleetFuelEntry::class);
    }

    public function maintenances()
    {
        return $this->hasMany(FleetMaintenance::class);
    }

    public function trips()
    {
        return $this->hasMany(FleetTrip::class);
    }
}
