<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\JourneyApprovalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $decided_at
 */
class JourneyApproval extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JourneyApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'journey_day_id',
        'journey_entry_id',
        'level',
        'status',
        'approver_id',
        'decided_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JourneyDay, $this>
     */
    public function journeyDay(): BelongsTo
    {
        return $this->belongsTo(JourneyDay::class);
    }

    /**
     * @return BelongsTo<JourneyEntry, $this>
     */
    public function journeyEntry(): BelongsTo
    {
        return $this->belongsTo(JourneyEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
