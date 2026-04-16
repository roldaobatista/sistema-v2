<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TravelAdvanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $amount
 * @property Carbon|null $paid_at
 */
class TravelAdvance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TravelAdvanceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'travel_request_id', 'user_id',
        'amount', 'status', 'paid_at', 'approved_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TravelRequest, $this>
     */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
