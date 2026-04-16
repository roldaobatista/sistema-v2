<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $day_of_week
 * @property bool|null $is_active
 */
class BusinessHour extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'day_of_week', 'start_time', 'end_time', 'is_active'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_active' => 'boolean',
        ];

    }
}
