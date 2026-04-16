<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $local_timestamp
 * @property Carbon|null $server_timestamp
 * @property array<int|string, mixed>|null $payload
 */
class OfflineSyncLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'uuid', 'event_type',
        'status', 'local_timestamp', 'server_timestamp',
        'payload', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'local_timestamp' => 'datetime',
            'server_timestamp' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
