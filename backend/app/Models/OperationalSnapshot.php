<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OperationalSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalSnapshot extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<OperationalSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'status',
        'alerts_count',
        'health_payload',
        'metrics_payload',
        'alerts_payload',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'alerts_count' => 'integer',
            'health_payload' => 'array',
            'metrics_payload' => 'array',
            'alerts_payload' => 'array',
            'captured_at' => 'datetime',
        ];

    }
}
