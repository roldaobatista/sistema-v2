<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $date
 */
class TenantHoliday extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'date', 'name'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];

    }
}
