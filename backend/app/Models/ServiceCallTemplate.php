<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $priority
 * @property string|null $observations
 * @property array|null $equipment_ids
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ServiceCall> $serviceCalls
 */
class ServiceCallTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'priority', 'observations',
        'equipment_ids', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'equipment_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function serviceCalls(): HasMany
    {
        return $this->hasMany(ServiceCall::class, 'template_id');
    }
}
