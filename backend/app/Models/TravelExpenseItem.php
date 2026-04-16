<?php

namespace App\Models;

use Database\Factories\TravelExpenseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $amount
 * @property Carbon|null $expense_date
 * @property bool|null $is_within_policy
 */
class TravelExpenseItem extends Model
{
    /** @use HasFactory<TravelExpenseItemFactory> */
    use HasFactory;

    protected $fillable = [
        'travel_expense_report_id', 'type', 'description',
        'amount', 'expense_date', 'receipt_path', 'is_within_policy',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'is_within_policy' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TravelExpenseReport, $this>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(TravelExpenseReport::class, 'travel_expense_report_id');
    }
}
