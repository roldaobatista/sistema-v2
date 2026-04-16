<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int|string, mixed>|null $bounds
 * @property int|null $zoom_min
 * @property int|null $zoom_max
 * @property numeric-string|null $estimated_size_mb
 * @property bool|null $is_active
 */
class OfflineMapRegion extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'offline_map_regions';

    protected $fillable = [
        'tenant_id',
        'name',
        'bounds',
        'zoom_min',
        'zoom_max',
        'estimated_size_mb',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'bounds' => 'array',
            'zoom_min' => 'integer',
            'zoom_max' => 'integer',
            'estimated_size_mb' => 'decimal:2',
            'is_active' => 'boolean',
        ];

    }

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
