<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $events
 * @property bool|null $is_active
 * @property int|null $failure_count
 * @property Carbon|null $last_triggered_at
 */
class Webhook extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'url', 'events', 'secret',
        'is_active', 'failure_count', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'failure_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];

    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}
