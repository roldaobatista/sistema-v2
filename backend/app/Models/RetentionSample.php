<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int|null $retention_days
 * @property Carbon|null $expires_at
 * @property Carbon|null $stored_at
 */
class RetentionSample extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'work_order_id', 'sample_code',
        'description', 'location', 'retention_days',
        'expires_at', 'status', 'stored_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
            'expires_at' => 'date',
            'stored_at' => 'datetime',
        ];

    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
