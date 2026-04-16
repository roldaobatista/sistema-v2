<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $hours
 * @property numeric-string|null $balance_before
 * @property numeric-string|null $balance_after
 * @property Carbon|null $reference_date
 * @property Carbon|null $expired_at
 */
class HourBankTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'journey_entry_id',
        'type', 'hours', 'balance_before', 'balance_after',
        'reference_date', 'expired_at', 'payout_payroll_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'reference_date' => 'date',
            'expired_at' => 'datetime',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journeyEntry(): BelongsTo
    {
        return $this->belongsTo(JourneyEntry::class);
    }

    public function payoutPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payout_payroll_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeExpiries($query)
    {
        return $query->where('type', 'expiry');
    }
}
