<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $original_clock_in
 * @property Carbon|null $original_clock_out
 * @property Carbon|null $adjusted_clock_in
 * @property Carbon|null $adjusted_clock_out
 * @property Carbon|null $decided_at
 */
class TimeClockAdjustment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'time_clock_entry_id', 'requested_by', 'approved_by',
        'original_clock_in', 'original_clock_out',
        'adjusted_clock_in', 'adjusted_clock_out',
        'reason', 'status', 'rejection_reason', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'original_clock_in' => 'datetime',
            'original_clock_out' => 'datetime',
            'adjusted_clock_in' => 'datetime',
            'adjusted_clock_out' => 'datetime',
            'decided_at' => 'datetime',
        ];

    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class, 'time_clock_entry_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
