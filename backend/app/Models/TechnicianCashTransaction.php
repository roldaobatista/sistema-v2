<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $amount
 * @property numeric-string|null $balance_after
 * @property Carbon|null $transaction_date
 */
class TechnicianCashTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const METHOD_CASH = 'cash';

    public const METHOD_CORPORATE_CARD = 'corporate_card';

    protected $fillable = [
        'tenant_id', 'fund_id', 'type', 'payment_method', 'amount', 'balance_after',
        'expense_id', 'work_order_id', 'created_by',
        'description', 'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(TechnicianCashFund::class, 'fund_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
