<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $events
 * @property bool|null $is_active
 * @property Carbon|null $last_triggered_at
 * @property int|null $failure_count
 */
class WebhookConfig extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'url', 'events', 'secret',
        'is_active', 'last_triggered_at', 'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'failure_count' => 'integer',
        ];

    }

    protected $hidden = ['secret'];
}
