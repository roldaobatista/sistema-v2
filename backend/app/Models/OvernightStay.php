<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OvernightStayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $stay_date
 * @property numeric-string|null $cost
 */
class OvernightStay extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<OvernightStayFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'travel_request_id', 'user_id',
        'stay_date', 'hotel_name', 'city', 'state',
        'cost', 'receipt_path', 'status',
    ];

    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'cost' => 'decimal:2',
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
}
