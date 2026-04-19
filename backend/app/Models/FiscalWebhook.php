<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $events
 * @property bool|null $active
 * @property Carbon|null $last_triggered_at
 */
class FiscalWebhook extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'url', 'events', 'secret', 'active',
        'failure_count', 'last_triggered_at',
    ];

    protected $attributes = [
        'events' => '["authorized","cancelled","rejected"]',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];

    }

    protected $hidden = ['secret'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
