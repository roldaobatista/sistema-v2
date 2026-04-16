<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DataExportJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $source_modules
 * @property array<int|string, mixed>|null $filters
 * @property int|null $file_size_bytes
 * @property int|null $rows_exported
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $last_scheduled_at
 */
class DataExportJob extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DataExportJobFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'analytics_dataset_id',
        'created_by',
        'name',
        'status',
        'source_modules',
        'filters',
        'output_format',
        'output_path',
        'file_size_bytes',
        'rows_exported',
        'started_at',
        'completed_at',
        'error_message',
        'scheduled_cron',
        'last_scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'source_modules' => 'array',
            'filters' => 'array',
            'file_size_bytes' => 'integer',
            'rows_exported' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_scheduled_at' => 'datetime',
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
     * @return BelongsTo<AnalyticsDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(AnalyticsDataset::class, 'analytics_dataset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
