<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $recipients
 * @property array<int|string, mixed>|null $filters
 * @property bool|null $is_active
 * @property Carbon|null $last_sent_at
 * @property Carbon|null $next_send_at
 */
class ScheduledReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'report_type', 'frequency', 'recipients',
        'filters', 'format', 'is_active', 'last_sent_at', 'next_send_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'filters' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'date',
            'next_send_at' => 'date',
        ];

    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
