<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property numeric-string|null $amount
 * @property numeric-string|null $paid_amount
 */
class AccountReceivableInstallment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'account_receivable_id', 'installment_number', 'due_date', 'amount', 'paid_amount', 'status', 'paid_at',
        'psp_external_id', 'psp_status', 'psp_boleto_url', 'psp_boleto_barcode', 'psp_pix_qr_code', 'psp_pix_copy_paste',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AccountReceivable, $this>
     */
    public function accountReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountReceivable::class);
    }
}
