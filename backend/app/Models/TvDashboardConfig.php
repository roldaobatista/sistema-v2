<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_default
 * @property bool|null $alert_sound
 * @property array<int|string, mixed>|null $widgets
 * @property int|null $rotation_interval
 * @property int|null $technician_offline_minutes
 * @property int|null $unattended_call_minutes
 * @property int|null $kpi_refresh_seconds
 * @property int|null $alert_refresh_seconds
 * @property int|null $cache_ttl_seconds
 */
class TvDashboardConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_default',
        'default_mode',
        'rotation_interval',
        'camera_grid',
        'alert_sound',
        'kiosk_pin',
        'technician_offline_minutes',
        'unattended_call_minutes',
        'kpi_refresh_seconds',
        'alert_refresh_seconds',
        'cache_ttl_seconds',
        'widgets',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'alert_sound' => 'boolean',
            'widgets' => 'array',
            'rotation_interval' => 'integer',
            'technician_offline_minutes' => 'integer',
            'unattended_call_minutes' => 'integer',
            'kpi_refresh_seconds' => 'integer',
            'alert_refresh_seconds' => 'integer',
            'cache_ttl_seconds' => 'integer',
        ];

    }

    protected $hidden = [
        'kiosk_pin', // Pin nunca deve ser vazado via API
    ];
}
