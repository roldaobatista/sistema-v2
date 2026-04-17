<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property bool|null $is_active
 * @property Carbon|null $last_triggered_at
 */
class InmetroWebhook extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'event_type', 'url', 'secret',
        'is_active', 'failure_count', 'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    protected $hidden = ['secret'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('event_type', $event);
    }
}
