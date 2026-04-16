<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $allowed_days
 * @property bool|null $is_active
 */
class AccessRestriction extends Model
{
    use BelongsToTenant;

    protected $table = 'access_time_restrictions';

    protected $fillable = [
        'tenant_id', 'role_name', 'allowed_days',
        'start_time', 'end_time', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_days' => 'array',
            'is_active' => 'boolean',
        ];

    }
}
