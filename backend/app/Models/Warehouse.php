<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $code
 * @property string|null $type
 * @property int|null $user_id
 * @property int|null $vehicle_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read FleetVehicle|null $vehicle
 * @property-read Collection<int, WarehouseStock> $stocks
 */
class Warehouse extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_TECHNICIAN = 'technician';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'user_id',
        'vehicle_id',
        'is_active',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function isTechnician(): bool
    {
        return $this->type === self::TYPE_TECHNICIAN;
    }

    public function isVehicle(): bool
    {
        return $this->type === self::TYPE_VEHICLE;
    }

    public function isCentral(): bool
    {
        return $this->type === self::TYPE_FIXED && ! $this->user_id && ! $this->vehicle_id;
    }
}
