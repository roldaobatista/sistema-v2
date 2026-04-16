<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 * @property Carbon|null $next_date
 * @property numeric-string|null $cost
 * @property int|null $odometer
 */
class FleetMaintenance extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'fleet_id', 'type', 'description', 'date',
        'cost', 'odometer', 'next_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'next_date' => 'date',
            'cost' => 'decimal:2',
            'odometer' => 'integer',
        ];
    }

    public function fleet(): BelongsTo
    {
        return $this->belongsTo(Fleet::class);
    }
}
