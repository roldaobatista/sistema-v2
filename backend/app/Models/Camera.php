<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $is_active
 * @property int|null $position
 */
class Camera extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'stream_url',
        'is_active',
        'position',
        'location',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];

    }
}
