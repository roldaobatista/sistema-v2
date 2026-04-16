<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $amount
 * @property Carbon|null $payment_date
 */
class AccountPayablePayment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'account_payable_id', 'installment_id', 'amount', 'payment_date', 'payment_method', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AccountPayable, $this>
     */
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    /**
     * @return BelongsTo<AccountPayableInstallment, $this>
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(AccountPayableInstallment::class, 'installment_id');
    }
}
