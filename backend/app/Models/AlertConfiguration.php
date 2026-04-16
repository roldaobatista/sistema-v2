<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_enabled
 * @property array<int|string, mixed>|null $channels
 * @property array<int|string, mixed>|null $recipients
 * @property array<int|string, mixed>|null $escalation_recipients
 * @property numeric-string|null $threshold_amount
 */
class AlertConfiguration extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'alert_type', 'is_enabled', 'channels',
        'days_before', 'cron_expression', 'recipients',
        'escalation_hours', 'escalation_recipients', 'blackout_start', 'blackout_end', 'threshold_amount',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'channels' => 'array',
            'recipients' => 'array',
            'escalation_recipients' => 'array',
            'threshold_amount' => 'decimal:2',
        ];
    }
}
