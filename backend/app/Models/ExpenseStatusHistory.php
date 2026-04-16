<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ExpenseStatus|null $from_status
 * @property ExpenseStatus|null $to_status
 */
class ExpenseStatusHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'expense_status_history';

    protected $fillable = [
        'tenant_id',
        'expense_id',
        'changed_by',
        'from_status',
        'to_status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ExpenseStatus::class,
            'to_status' => ExpenseStatus::class,
        ];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
