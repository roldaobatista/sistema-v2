<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RoutePlanFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $stops
 * @property numeric-string|null $total_distance_km
 * @property int|null $estimated_duration_min
 */
class RoutePlan extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<RoutePlanFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'technician_id', 'plan_date', 'stops',
        'total_distance_km', 'estimated_duration_min', 'status',
    ];

    protected function casts(): array
    {
        return [
            'stops' => 'array',
            'total_distance_km' => 'decimal:2',
            'estimated_duration_min' => 'integer',
        ];

    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function planDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
