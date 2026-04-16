<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int|null $response_time_minutes
 * @property int|null $resolution_time_minutes
 * @property string|null $priority
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlaPolicy extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'response_time_minutes',
        'resolution_time_minutes', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'response_time_minutes' => 'integer',
            'resolution_time_minutes' => 'integer',
            'is_active' => 'boolean',
        ];

    }

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';
}
