<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $client_data
 * @property array<int|string, mixed>|null $server_data
 * @property Carbon|null $resolved_at
 */
class SyncConflictLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'work_order_id',
        'conflict_type',
        'client_data',
        'server_data',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'client_data' => 'array',
            'server_data' => 'array',
            'resolved_at' => 'datetime',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
