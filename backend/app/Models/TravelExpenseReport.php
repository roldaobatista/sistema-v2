<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TravelExpenseReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string|null $total_expenses
 * @property numeric-string|null $total_advances
 * @property numeric-string|null $balance
 */
class TravelExpenseReport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TravelExpenseReportFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'travel_request_id', 'created_by',
        'total_expenses', 'total_advances', 'balance',
        'status', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'total_expenses' => 'decimal:2',
            'total_advances' => 'decimal:2',
            'balance' => 'decimal:2',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TravelExpenseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TravelExpenseItem::class);
    }

    public function recalculate(): void
    {
        $totalExpenses = (float) $this->items()->sum('amount');
        $totalAdvances = (float) $this->travelRequest->advances()->where('status', 'paid')->sum('amount');
        $balance = $totalAdvances - $totalExpenses;

        $this->update([
            'total_expenses' => $totalExpenses,
            'total_advances' => $totalAdvances,
            'balance' => $balance,
        ]);
    }
}
