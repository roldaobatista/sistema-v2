<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'technician_id', 'date', 'shift_type', 'start_time', 'end_time', 'region', 'notes',
    ];

    public function setDateAttribute(string|\DateTimeInterface|null $value): void
    {
        $this->attributes['date'] = $value
            ? Carbon::parse($value)->toDateString()
            : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
