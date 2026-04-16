<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AnalyticsDatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $source_modules
 * @property array<int|string, mixed>|null $query_definition
 * @property int|null $cache_ttl_minutes
 * @property Carbon|null $last_refreshed_at
 * @property bool|null $is_active
 */
class AnalyticsDataset extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AnalyticsDatasetFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'source_modules',
        'query_definition',
        'refresh_strategy',
        'cache_ttl_minutes',
        'last_refreshed_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source_modules' => 'array',
            'query_definition' => 'array',
            'cache_ttl_minutes' => 'integer',
            'last_refreshed_at' => 'datetime',
            'is_active' => 'boolean',
        ];

    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DataExportJob, $this>
     */
    public function exportJobs(): HasMany
    {
        return $this->hasMany(DataExportJob::class);
    }
}
