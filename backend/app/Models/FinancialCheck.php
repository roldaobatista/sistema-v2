<?php

namespace App\Models;

use App\Enums\FinancialCheckStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $amount
 * @property Carbon|null $due_date
 * @property FinancialCheckStatus|null $status
 */
class FinancialCheck extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'type', 'number', 'bank',
        'amount', 'due_date', 'issuer', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => FinancialCheckStatus::class,
        ];
    }

    public const TYPE_RECEIVED = 'received';

    public const TYPE_ISSUED = 'issued';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DEPOSITED = 'deposited';

    public const STATUS_COMPENSATED = 'compensated';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CUSTODY = 'custody';
}
