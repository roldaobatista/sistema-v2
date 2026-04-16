<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property numeric-string|null $liters
 * @property numeric-string|null $cost
 * @property int|null $odometer
 */
class FleetFuelEntry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'fleet_id', 'date', 'fuel_type', 'liters',
        'cost', 'odometer', 'station', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'liters' => 'decimal:2',
            'cost' => 'decimal:2',
            'odometer' => 'integer',
        ];
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
